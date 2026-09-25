<?php

namespace App\Listeners;

use App\Events\LinkSnapshotCreated;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use App\Models\ContentChunk;
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
     * The link's other snapshots lose their chunks first, since only the
     * latest text is searchable. Their `content_text` stays, so their
     * chunks can always be rebuilt.
     */
    public function handle(LinkSnapshotCreated $event): void
    {
        $snapshot = $event->snapshot;

        ContentChunk::query()
            ->where('link_id', $snapshot->link_id)
            ->where('link_snapshot_id', '!=', $snapshot->id)
            ->delete();

        SummarizeSnapshotJob::dispatch($snapshot);

        Bus::chain([
            new ChunkSnapshotJob($snapshot),
            new EmbedChunksJob($snapshot),
        ])->onQueue('enrichment')->dispatch();
    }
}
