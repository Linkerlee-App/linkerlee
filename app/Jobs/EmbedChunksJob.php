<?php

namespace App\Jobs;

use App\Enrichment\EmbeddingManager;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a snapshot's content chunks, the last step of the enrichment chain.
 *
 * Only chunks with no embedding, or one from a different model, are sent, in
 * batches of `enrichment.embedding.batch_size`, so re-running it after a
 * partial failure picks up where it left off and never re-embeds a chunk
 * already embedded by the model in use.
 */
class EmbedChunksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

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
     * it has one, before it is embedded; the stored chunk text is not.
     */
    public function handle(EmbeddingManager $embeddings): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        $provider = $embeddings->provider($this->model);
        $model = $provider->model();
        $driver = config('enrichment.embedding.driver');
        $prefix = (string) config("enrichment.embedding.{$driver}.document_prefix", '');

        ContentChunk::query()
            ->where('link_snapshot_id', $this->snapshot->id)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('embedding')
                ->orWhereNull('embedding_model')
                ->orWhere('embedding_model', '!=', $model))
            ->chunkById((int) config('enrichment.embedding.batch_size'), function (Collection $chunks) use ($provider, $model, $prefix): void {
                $vectors = $provider->embed($chunks->map(fn (ContentChunk $chunk): string => $prefix.$chunk->text)->values()->all());

                foreach ($chunks->values() as $index => $chunk) {
                    $chunk->update([
                        'embedding' => $vectors[$index],
                        'embedding_model' => $model,
                    ]);
                }
            });
    }
}
