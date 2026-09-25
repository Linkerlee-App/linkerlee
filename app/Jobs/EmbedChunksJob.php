<?php

namespace App\Jobs;

use App\Enrichment\EmbeddingManager;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a snapshot's content chunks, the last step of the chunk-then-embed
 * chain (and dispatched on its own after a summary rewrites chunk 0).
 *
 * Only chunks with no embedding, or one from a different model, are sent.
 * Each run embeds at most one batch of `enrichment.embedding.batch_size` and
 * re-dispatches itself while more remain, so a huge page never outruns the
 * job's timeout, and re-running it after a partial failure picks up where it
 * left off without re-embedding a chunk already embedded by the model in use.
 */
class EmbedChunksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Kept below the database queue's `retry_after` (90s), so a slow batch is
     * killed before the job could be handed out twice.
     */
    public int $timeout = 80;

    /**
     * A link force-deleted (and its snapshots with it) before the job runs
     * makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  string|null  $model  Embed with this model instead of the configured default.
     */
    public function __construct(public LinkSnapshot $snapshot, public ?string $model = null)
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
     * Skipped when the snapshot is no longer its live link's latest. Each
     * text is prefixed with the configured driver's `document_prefix`, when
     * it has one and the model in use is that driver's default (the prefix
     * is model-specific, so an overriding model gets none), before it is
     * embedded; the stored chunk text is not.
     */
    public function handle(EmbeddingManager $embeddings): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        $provider = $embeddings->provider($this->model);
        $model = $provider->model();
        $driver = config('enrichment.embedding.driver');
        $prefix = $model === $embeddings->driver()->model()
            ? (string) config("enrichment.embedding.{$driver}.document_prefix", '')
            : '';

        $chunks = $this->pendingChunks($model)
            ->orderBy('id')
            ->limit(max(1, (int) config('enrichment.embedding.batch_size')))
            ->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $vectors = $provider->embed($chunks->map(fn (ContentChunk $chunk): string => $prefix.$chunk->text)->values()->all());

        foreach ($chunks->values() as $index => $chunk) {
            $chunk->update([
                'embedding' => $vectors[$index],
                'embedding_model' => $model,
            ]);
        }

        if ($this->pendingChunks($model)->exists()) {
            self::dispatch($this->snapshot, $this->model)->onConnection($this->connection);
        }
    }

    /**
     * The snapshot's chunks with no embedding, or one from another model.
     *
     * @return Builder<ContentChunk>
     */
    private function pendingChunks(string $model): Builder
    {
        return ContentChunk::query()
            ->where('link_snapshot_id', $this->snapshot->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('embedding')
                ->orWhereNull('embedding_model')
                ->orWhere('embedding_model', '!=', $model));
    }
}
