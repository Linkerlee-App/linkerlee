<?php

namespace App\Scraping;

use App\Enums\ExtractionStatus;
use App\Events\LinkSnapshotCreated;
use App\Health\ContentSimilarity;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\LinkSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns an {@see ExtractionResult} into the link's stored extraction state:
 * a new {@see LinkSnapshot} when the text changed, refreshed bookkeeping when
 * it did not, and a recorded error when the extraction failed.
 *
 * Every caller (content extraction, the health check) goes through here under
 * one per-link lock, so two jobs finishing at once cannot both decide the text
 * is new and store it twice.
 */
class SnapshotRecorder
{
    /**
     * The longest extraction error stored on a link, in characters.
     */
    public const MAX_ERROR_LENGTH = 1000;

    /**
     * How long the per-link lock is held at most, and how long a second
     * recorder waits for it, in seconds.
     */
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    /**
     * The length of the varchar columns scraped strings are written to.
     */
    private const MAX_STRING_LENGTH = 255;

    public function __construct(private ContentSimilarity $similarity) {}

    /**
     * Records the result under the per-link lock. A failure updates only the
     * status and error. Text that hashes the same as the latest snapshot only
     * refreshes `extracted_at` and the validators. New text becomes a snapshot,
     * and {@see LinkSnapshotCreated} fires once it is committed; when it
     * replaces an earlier snapshot, `content_changed_at` is stamped too.
     *
     * When the link's URL no longer matches the URL that was extracted (it was
     * edited while the extraction ran), nothing is written, a fresh
     * {@see ExtractContentJob} is queued and the outcome is Superseded. That
     * re-dispatch happens only after the lock above has been released: on the
     * `sync` queue connection the dispatch runs the job inline, and it needs
     * its own turn at the same lock, so dispatching it from inside
     * {@see self::recordLocked()} would make it queue behind itself and time
     * out.
     *
     * @param  string  $extractedUrl  The URL the result was extracted from.
     * @param  bool  $compareForNoise  Set by the health check only: text whose shingle similarity to
     *                                 the latest snapshot reaches `link_health.noise_similarity` is
     *                                 treated as unchanged (a timestamp, an ad slot, a view counter).
     *                                 Content extraction never sets it, so text fetched after the
     *                                 user edits the URL always replaces the old page's.
     *
     * @throws LockTimeoutException when another recorder holds the lock for too long
     */
    public function record(Link $link, ExtractionResult $result, string $extractedUrl, bool $compareForNoise = false): RecordOutcome
    {
        $outcome = Cache::lock("link-snapshot:{$link->id}", self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): RecordOutcome => $this->recordLocked($link, $result, $extractedUrl, $compareForNoise));

        if ($outcome === RecordOutcome::Superseded) {
            ExtractContentJob::dispatch($link)->afterCommit();
        }

        return $outcome;
    }

    /**
     * Deletes the link's snapshots beyond `scraping.keep_snapshots`. The
     * extracted text is the source of truth, so two snapshots are always
     * kept first: the latest (`latest_snapshot_id`), and the first one ever
     * stored (the oldest by `fetched_at`), which holds the page as it was
     * saved and must survive a page (a parked domain, say) whose text keeps
     * changing. The rest of the limit goes to the newest other snapshots.
     * With a limit of one, only the latest is kept.
     */
    public function pruneSnapshots(Link $link): void
    {
        $keep = max(1, (int) config('scraping.keep_snapshots', 5));

        $firstId = $link->snapshots()->orderBy('fetched_at')->orderBy('id')->value('id');

        $keptIds = collect([$link->latest_snapshot_id, $firstId])
            ->filter()
            ->unique()
            ->take($keep)
            ->values();

        $remaining = $keep - $keptIds->count();

        if ($remaining > 0) {
            $keptIds = $keptIds->merge(
                $link->snapshots()
                    ->whereKeyNot($keptIds->all())
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->limit($remaining)
                    ->pluck('id'),
            );
        }

        $link->snapshots()->whereKeyNot($keptIds->all())->delete();
    }

    /**
     * Caps an error message at {@see self::MAX_ERROR_LENGTH} characters.
     */
    public static function truncateError(?string $error): ?string
    {
        return $error === null ? null : mb_substr($error, 0, self::MAX_ERROR_LENGTH);
    }

    /**
     * The body of {@see self::record()}, run while holding the link's lock.
     *
     * The link is re-read here rather than with refresh(), so a link
     * force-deleted after the caller loaded it fails quietly with no writes
     * instead of throwing. Like refresh(), the caller's instance ends up with
     * the current attributes and its loaded relations reloaded.
     *
     * A link's very first snapshot also schedules its first health check,
     * `link_health.initial_interval_days` out. So does a failed extraction
     * on a link that has no check scheduled yet: a PDF, a page dead at save
     * or a bot wall never gets a first snapshot, but still needs checking.
     */
    private function recordLocked(Link $link, ExtractionResult $result, string $extractedUrl, bool $compareForNoise): RecordOutcome
    {
        $fresh = Link::withTrashed()->find($link->getKey());

        if ($fresh === null) {
            return RecordOutcome::Failed;
        }

        $loadedRelations = array_values(array_diff(array_keys($link->getRelations()), ['pivot']));

        $link->setRawAttributes($fresh->getAttributes(), true)
            ->setRelations([])
            ->load($loadedRelations);

        if ($link->link !== $extractedUrl) {
            return RecordOutcome::Superseded;
        }

        if (! $result->isOk()) {
            $link->forceFill([
                'extraction_status' => $result->status,
                'extraction_error' => self::truncateError($result->error),
                ...($link->next_check_at === null ? self::firstCheck(now()) : []),
            ])->save();

            return RecordOutcome::Failed;
        }

        if ($link->latestSnapshot?->content_hash === $result->contentHash || ($compareForNoise && $this->isNoise($link->latestSnapshot, $result))) {
            $link->forceFill([
                'extraction_status' => ExtractionStatus::Ok,
                'extraction_error' => null,
                'extracted_at' => now(),
                'etag' => self::capString($result->etag),
                'last_modified' => self::capString($result->lastModified),
            ])->save();

            return RecordOutcome::Unchanged;
        }

        $isFirstSnapshot = $link->latest_snapshot_id === null;

        $snapshot = DB::transaction(function () use ($link, $result, $isFirstSnapshot): LinkSnapshot {
            $fetchedAt = now();

            $snapshot = $link->snapshots()->create([
                'extractor' => $result->extractor,
                'http_status' => $result->httpStatus,
                'final_url' => $result->finalUrl,
                'title' => $result->title,
                'author' => self::capString($result->author),
                'published_at' => $result->publishedAt,
                'content_text' => $result->contentText,
                'content_hash' => $result->contentHash,
                'word_count' => $result->wordCount,
                'metadata' => [
                    'etag' => self::capString($result->etag),
                    'last_modified' => self::capString($result->lastModified),
                    'extractor' => $result->extractor,
                ],
                'fetched_at' => $fetchedAt,
            ]);

            $link->forceFill([
                'latest_snapshot_id' => $snapshot->id,
                'extraction_status' => ExtractionStatus::Ok,
                'extraction_error' => null,
                'extracted_at' => $fetchedAt,
                'etag' => self::capString($result->etag),
                'last_modified' => self::capString($result->lastModified),
                ...($isFirstSnapshot ? self::firstCheck($fetchedAt) : ['content_changed_at' => $fetchedAt]),
            ])->save();

            $link->setRelation('latestSnapshot', $snapshot);

            $this->pruneSnapshots($link);

            return $snapshot;
        });

        LinkSnapshotCreated::dispatch($snapshot);

        return RecordOutcome::Created;
    }

    /**
     * Whether the new text differs from the latest snapshot's only by noise:
     * its shingle similarity reaches `link_health.noise_similarity`. With no
     * snapshot yet there is nothing to compare against, so it is never noise.
     */
    private function isNoise(?LinkSnapshot $latest, ExtractionResult $result): bool
    {
        if ($latest === null) {
            return false;
        }

        return $this->similarity->jaccard((string) $result->contentText, (string) $latest->content_text)
            >= (float) config('link_health.noise_similarity');
    }

    /**
     * The schedule of a link's first health check,
     * `link_health.initial_interval_days` after the given moment.
     *
     * @return array{next_check_at: CarbonInterface, check_interval_days: int}
     */
    private static function firstCheck(CarbonInterface $from): array
    {
        $initialIntervalDays = (int) config('link_health.initial_interval_days');

        return [
            'next_check_at' => $from->copy()->addDays($initialIntervalDays),
            'check_interval_days' => $initialIntervalDays,
        ];
    }

    /**
     * Caps a scraped string at the varchar length, so one junk byline or an
     * over-long header cannot make the whole write fail.
     */
    private static function capString(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, self::MAX_STRING_LENGTH);
    }
}
