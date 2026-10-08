<?php

namespace App\Listeners;

use App\Events\LinkSnapshotCreated;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use Illuminate\Support\Facades\Bus;

/**
 * Enriches a newly stored snapshot on the `enrichment` queue, so a provider
 * outage never holds up extraction. Two independent units are dispatched:
 * {@see SummarizeSnapshotJob} on its own, and a chunk-then-embed chain, so a
 * summary failure never blocks chunking and embedding. A summary that lands
 * after the chunks rewrites chunk 0 itself.
 *
 * Registered explicitly in AppServiceProvider (event discovery is off), so
 * each event dispatches exactly one of each.
 */
class EnrichSnapshot
{
    /**
     * The link's older chunks are replaced by {@see ChunkSnapshotJob} in the
     * same transaction that inserts the new ones, so the link is never left
     * without chunks while the chain is pending. Older snapshots keep their
     * `content_text`, so their chunks can always be rebuilt.
     */
    public function handle(LinkSnapshotCreated $event): void
    {
        $snapshot = $event->snapshot;

        SummarizeSnapshotJob::dispatch($snapshot);

        Bus::chain([
            new ChunkSnapshotJob($snapshot),
            new EmbedChunksJob($snapshot),
        ])->onQueue('enrichment')->dispatch();
    }
}
