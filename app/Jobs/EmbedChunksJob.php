<?php

namespace App\Jobs;

use App\Casts\VectorCast;
use App\Enrichment\EmbeddingManager;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Embeds a snapshot's content chunks, the last step of the chunk-then-embed
 * chain (and dispatched on its own after a summary rewrites chunk 0).
 *
 * Only chunks with no embedding, or one from a different model, are sent.
 * Each run embeds at most one batch of `enrichment.embedding.batch_size` and
 * re-dispatches itself while more remain, so a huge page never outruns the
 * job's timeout, and re-running it after a partial failure picks up where it
 * left off without re-embedding a chunk already embedded by the model in use.
 *
 * A run only looks at chunks after `$afterId`, its cursor, and hands the last
 * id it embedded to the next run, so one loop walks a snapshot's chunks
 * forward once and ends. Two loops on different models (a `reembed --model`
 * beside the default chain) can't keep undoing each other's batches. As a
 * safety net, a loop stops re-dispatching after {@see self::maxHops()} runs
 * and logs a warning.
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
     * @param  int  $afterId  Only embed chunks with a greater id: the last id the previous run embedded.
     * @param  int  $hop  How many runs of this loop came before this one.
     */
    public function __construct(
        public LinkSnapshot $snapshot,
        public ?string $model = null,
        public int $afterId = 0,
        public int $hop = 0,
    ) {
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
     *
     * Each vector is written only while its chunk still holds the text that
     * was embedded. A chunk rewritten during the provider call (chunk 0,
     * by {@see SummarizeSnapshotJob}) keeps the null embedding its rewrite
     * set, so it stays pending and the EmbedChunksJob that rewrite
     * dispatches embeds it with its new text.
     * The write is a query-builder update, which skips model casts, so the
     * vector is formatted through {@see VectorCast} by hand.
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
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit(max(1, (int) config('enrichment.embedding.batch_size')))
            ->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $vectors = $provider->embed($chunks->map(fn (ContentChunk $chunk): string => $prefix.$chunk->text)->values()->all());

        foreach ($chunks->values() as $index => $chunk) {
            ContentChunk::query()
                ->whereKey($chunk->id)
                ->where('text', $chunk->text)
                ->update([
                    'embedding' => (new VectorCast)->set($chunk, 'embedding', $vectors[$index], []),
                    'embedding_model' => $model,
                ]);
        }

        $lastId = $chunks->last()->id;

        if (! $this->pendingChunks($model)->where('id', '>', $lastId)->exists()) {
            return;
        }

        if ($this->hop + 1 >= self::maxHops()) {
            Log::warning('EmbedChunksJob reached its hop cap; chunks are left pending.', [
                'link_snapshot_id' => $this->snapshot->id,
                'model' => $model,
                'after_id' => $lastId,
                'hop' => $this->hop,
            ]);

            return;
        }

        self::dispatch($this->snapshot, $this->model, $lastId, $this->hop + 1)->onConnection($this->connection);
    }

    /**
     * The most runs one loop may take: enough batches for a snapshot at
     * `enrichment.chunking.max_chunks`, plus one.
     */
    public static function maxHops(): int
    {
        $batchSize = max(1, (int) config('enrichment.embedding.batch_size'));

        return (int) ceil(max(1, (int) config('enrichment.chunking.max_chunks')) / $batchSize) + 1;
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
