<?php

namespace App\Jobs;

use App\Enrichment\Chunker;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a snapshot's content chunks with a fresh cut of its title,
 * summary and text, the second step of the enrichment chain.
 *
 * The old chunks are deleted and the new ones inserted in one transaction,
 * all with a null embedding for {@see EmbedChunksJob} to fill, so re-running
 * it never duplicates chunks.
 */
class ChunkSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * A link force-deleted (and its snapshots with it) before the job runs
     * makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public LinkSnapshot $snapshot)
    {
        $this->onQueue('enrichment');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * Skipped when the snapshot is no longer its live link's latest.
     */
    public function handle(Chunker $chunker): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        $chunks = $chunker->chunk(
            $this->snapshot->title ?? '',
            $this->snapshot->summary,
            (string) $this->snapshot->content_text,
        );

        $now = now();

        $rows = array_map(fn (array $chunk, int $ordinal): array => [
            'link_id' => $this->snapshot->link_id,
            'link_snapshot_id' => $this->snapshot->id,
            'ordinal' => $ordinal,
            'text' => $chunk['text'],
            'token_count' => $chunk['token_count'],
            'embedding' => null,
            'embedding_model' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunks, array_keys($chunks));

        DB::transaction(function () use ($rows): void {
            ContentChunk::query()->where('link_snapshot_id', $this->snapshot->id)->delete();

            if ($rows !== []) {
                ContentChunk::query()->insert($rows);
            }
        });
    }
}
