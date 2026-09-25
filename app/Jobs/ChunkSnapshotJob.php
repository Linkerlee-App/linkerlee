<?php

namespace App\Jobs;

use App\Enrichment\Chunker;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a link's content chunks with a fresh cut of its current
 * snapshot's title, summary and text, the first step of the chunk-then-embed
 * chain.
 *
 * Every chunk of the link, from any snapshot, is deleted and the new ones
 * inserted in one transaction, all with a null embedding for
 * {@see EmbedChunksJob} to fill. Re-running it never duplicates chunks, and
 * the link is never left without chunks between the delete and the insert.
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
     * Skipped when the snapshot is no longer its live link's latest. That is
     * checked again under a lock on the link row, since a newer snapshot can
     * become current between the first check and the swap; the lock also
     * serialises the swap with {@see SummarizeSnapshotJob}'s chunk 0 rewrite,
     * and the summary is re-read under it so chunk 0 is never cut from a
     * stale one.
     */
    public function handle(Chunker $chunker): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        DB::transaction(function () use ($chunker): void {
            $link = Link::withTrashed()->whereKey($this->snapshot->link_id)->lockForUpdate()->first();

            if ($link === null || $link->trashed() || $link->latest_snapshot_id !== $this->snapshot->id) {
                return;
            }

            $this->snapshot->refresh();

            $chunks = $chunker->chunk(
                $this->snapshot->title ?? '',
                $this->snapshot->summary,
                (string) $this->snapshot->content_text,
            );

            ContentChunk::query()->where('link_id', $this->snapshot->link_id)->delete();

            if ($chunks !== []) {
                ContentChunk::query()->insert($this->rows($chunks));
            }
        });
    }

    /**
     * @param  list<array{text: string, token_count: int}>  $chunks
     * @return list<array<string, mixed>>
     */
    private function rows(array $chunks): array
    {
        $now = now();

        return array_map(fn (array $chunk, int $ordinal): array => [
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
    }
}
