<?php

namespace App\Console\Commands;

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Scraping\TransientExtractionException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Backfills and retries content extraction for existing links.
 *
 * With no options it targets links still `pending` — the backfill to run
 * once after deploying content extraction, so every link that predates it
 * gets a snapshot. `--failed` retries links that gave up, and `--link`
 * targets one link by id regardless of its status.
 */
class ExtractContentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkerlee:extract
        {--failed : Retry links whose extraction failed or was blocked, instead of pending ones. Ignored when --link is given.}
        {--link= : Extract exactly one link by id, regardless of its status. Wins over --failed.}
        {--sync : Run extraction inline instead of queuing it, for small backfills.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill or retry content extraction for existing links';

    public function handle(): int
    {
        if ($this->option('link') !== null) {
            return $this->extractOne((int) $this->option('link'));
        }

        $query = $this->option('failed')
            ? Link::query()->whereIn('extraction_status', [ExtractionStatus::Failed->value, ExtractionStatus::Blocked->value])
            : Link::query()->where('extraction_status', ExtractionStatus::Pending->value);

        return $this->option('sync')
            ? $this->extractSync($query)
            : $this->dispatchAll($query);
    }

    /**
     * Validates and processes the single link named by `--link`, ignoring
     * `--failed` and the status filter entirely.
     */
    private function extractOne(int $linkId): int
    {
        $link = Link::withTrashed()->find($linkId);

        if ($link === null) {
            $this->error("No link with id {$linkId}.");

            return Command::FAILURE;
        }

        if ($link->trashed()) {
            $this->error("Link {$linkId} is trashed.");

            return Command::FAILURE;
        }

        if ($this->option('sync')) {
            [$ok, $failed] = $this->extractLinkSync($link);

            $this->summarizeSync($ok, $failed);

            return Command::SUCCESS;
        }

        ExtractContentJob::dispatch($link);

        $this->summarizeDispatch(1);

        return Command::SUCCESS;
    }

    /**
     * Dispatches every link matched by the query onto the ingestion queue,
     * 200 at a time.
     */
    private function dispatchAll(Builder $query): int
    {
        $dispatched = 0;

        $query->chunkById(200, function ($links) use (&$dispatched): void {
            foreach ($links as $link) {
                ExtractContentJob::dispatch($link);
                $dispatched++;
            }
        });

        $this->summarizeDispatch($dispatched);

        return Command::SUCCESS;
    }

    /**
     * Runs extraction inline for every link matched by the query, 200 at a
     * time. A transient failure is recorded by the job itself and must not
     * abort the rest of the backfill, so it is caught here and counted as
     * failed like any other non-ok outcome.
     */
    private function extractSync(Builder $query): int
    {
        $ok = 0;
        $failed = 0;

        $query->chunkById(200, function ($links) use (&$ok, &$failed): void {
            foreach ($links as $link) {
                [$linkOk, $linkFailed] = $this->extractLinkSync($link);
                $ok += $linkOk;
                $failed += $linkFailed;
            }
        });

        $this->summarizeSync($ok, $failed);

        return Command::SUCCESS;
    }

    /**
     * Runs extraction for one link inline, through the same path a real
     * queue worker would use (`dispatchSync()` puts it on the `sync`
     * connection, so the job is fully serialized and restored — the
     * trashed check and `deleteWhenMissingModels` behave exactly as they
     * do on the real queue).
     *
     * @return array{int, int} the ok and failed counts contributed by this link, exactly one of which is 1
     */
    private function extractLinkSync(Link $link): array
    {
        try {
            ExtractContentJob::dispatchSync($link);
        } catch (TransientExtractionException) {
            // Already recorded by the job; fall through to the status check.
        }

        return $link->fresh()?->extraction_status === ExtractionStatus::Ok
            ? [1, 0]
            : [0, 1];
    }

    private function summarizeDispatch(int $count): void
    {
        $this->info(sprintf(
            'Dispatched %d %s to the ingestion queue.',
            $count,
            $count === 1 ? 'link' : 'links',
        ));
    }

    private function summarizeSync(int $ok, int $failed): void
    {
        $total = $ok + $failed;

        $this->info(sprintf(
            'Extracted %d %s (%d ok, %d failed).',
            $total,
            $total === 1 ? 'link' : 'links',
            $ok,
            $failed,
        ));
    }
}
