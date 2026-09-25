<?php

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Link;
use App\Scraping\ScrapingManager;
use App\Scraping\SnapshotRecorder;
use App\Scraping\TransientExtractionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extracts a link's article text and records it as a snapshot.
 *
 * Idempotent: re-running it on unchanged content refreshes the link's
 * bookkeeping without storing a second snapshot.
 */
class ExtractContentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * A link force-deleted before the job runs makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Link $link)
    {
        $this->onQueue('ingestion');
    }

    public function uniqueId(): string
    {
        return (string) $this->link->id;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /**
     * A trashed link is skipped. A transient failure is recorded and then
     * rethrown so the queue retries, except on the last attempt, where the
     * recorded failure stands.
     */
    public function handle(ScrapingManager $scraping, SnapshotRecorder $recorder): void
    {
        if ($this->link->trashed()) {
            return;
        }

        $result = $scraping->extractFor($this->link->link);

        $recorder->record($this->link, $result);

        if ($result->transient && $this->attempts() < $this->tries) {
            throw new TransientExtractionException($result->error ?? 'Transient extraction failure');
        }
    }

    /**
     * Marks the link failed. A query update rather than a model save, so a
     * link deleted in the meantime is a no-op instead of an error.
     */
    public function failed(Throwable $exception): void
    {
        Link::withTrashed()
            ->whereKey($this->link->getKey())
            ->update([
                'extraction_status' => ExtractionStatus::Failed,
                'extraction_error' => SnapshotRecorder::truncateError($exception->getMessage()),
            ]);
    }
}
