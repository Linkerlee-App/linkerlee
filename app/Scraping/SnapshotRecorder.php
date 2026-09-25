<?php

namespace App\Scraping;

use App\Enums\ExtractionStatus;
use App\Events\LinkSnapshotCreated;
use App\Models\Link;
use App\Models\LinkSnapshot;
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
     * Records the result under the per-link lock. A failure updates only the
     * status and error. Text that hashes the same as the latest snapshot only
     * refreshes `extracted_at` and the validators. New text becomes a snapshot,
     * and {@see LinkSnapshotCreated} fires once it is committed.
     *
     * @param  bool  $compareForNoise  Reserved for the health check. It has no effect yet.
     *
     * @throws LockTimeoutException when another recorder holds the lock for too long
     */
    public function record(Link $link, ExtractionResult $result, bool $compareForNoise = false): RecordOutcome
    {
        return Cache::lock("link-snapshot:{$link->id}", self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): RecordOutcome => $this->recordLocked($link, $result));
    }

    /**
     * Deletes the link's snapshots beyond `scraping.keep_snapshots`, newest
     * first by `fetched_at`. The latest snapshot always counts towards the
     * limit and is never deleted, even when it is not the newest.
     */
    public function pruneSnapshots(Link $link): void
    {
        $keep = max(1, (int) config('scraping.keep_snapshots', 5));
        $latestId = $link->latest_snapshot_id;

        $newestIds = $link->snapshots()
            ->when($latestId, fn ($query) => $query->whereKeyNot($latestId))
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->limit($latestId ? $keep - 1 : $keep)
            ->pluck('id');

        $keptIds = $latestId ? $newestIds->push($latestId) : $newestIds;

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
     */
    private function recordLocked(Link $link, ExtractionResult $result): RecordOutcome
    {
        $link->refresh();

        if (! $result->isOk()) {
            $link->forceFill([
                'extraction_status' => $result->status,
                'extraction_error' => self::truncateError($result->error),
            ])->save();

            return RecordOutcome::Failed;
        }

        if ($link->latestSnapshot?->content_hash === $result->contentHash) {
            $link->forceFill([
                'extraction_status' => ExtractionStatus::Ok,
                'extraction_error' => null,
                'extracted_at' => now(),
                'etag' => $result->etag,
                'last_modified' => $result->lastModified,
            ])->save();

            return RecordOutcome::Unchanged;
        }

        $snapshot = DB::transaction(function () use ($link, $result): LinkSnapshot {
            $fetchedAt = now();

            $snapshot = $link->snapshots()->create([
                'extractor' => $result->extractor,
                'http_status' => $result->httpStatus,
                'final_url' => $result->finalUrl,
                'title' => $result->title,
                'author' => $result->author,
                'published_at' => $result->publishedAt,
                'content_text' => $result->contentText,
                'content_hash' => $result->contentHash,
                'word_count' => $result->wordCount,
                'metadata' => [
                    'etag' => $result->etag,
                    'last_modified' => $result->lastModified,
                    'extractor' => $result->extractor,
                ],
                'fetched_at' => $fetchedAt,
            ]);

            $link->forceFill([
                'latest_snapshot_id' => $snapshot->id,
                'extraction_status' => ExtractionStatus::Ok,
                'extraction_error' => null,
                'extracted_at' => $fetchedAt,
                'etag' => $result->etag,
                'last_modified' => $result->lastModified,
            ])->save();

            $link->setRelation('latestSnapshot', $snapshot);

            $this->pruneSnapshots($link);

            return $snapshot;
        });

        LinkSnapshotCreated::dispatch($snapshot);

        return RecordOutcome::Created;
    }
}
