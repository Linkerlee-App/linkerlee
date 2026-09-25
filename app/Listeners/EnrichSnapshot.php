<?php

namespace App\Listeners;

use App\Events\LinkSnapshotCreated;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use App\Models\ContentChunk;
use Illuminate\Support\Facades\Bus;

/**
 * Enriches a newly stored snapshot: summarize, chunk, then embed, chained on
 * the `enrichment` queue so a provider outage never holds up extraction.
 *
 * Registered explicitly in AppServiceProvider (event discovery is off), so
 * each event dispatches exactly one chain.
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

        Bus::chain([
            new SummarizeSnapshotJob($snapshot),
            new ChunkSnapshotJob($snapshot),
            new EmbedChunksJob($snapshot),
        ])->onQueue('enrichment')->dispatch();
    }
}
