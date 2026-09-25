<?php

namespace App\Jobs;

use App\Enrichment\Chunker;
use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\NonRetryableProviderException;
use App\Enrichment\Providers\NullSummaryProvider;
use App\Enrichment\SummaryManager;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Facades\DB;

/**
 * Writes a snapshot's `summary` and `summary_model`.
 *
 * Runs independently of the chunk-then-embed chain, so a summary outage never
 * holds up chunking or embedding. When the snapshot already has chunks, a new
 * summary rewrites chunk 0 (title and summary), clears its embedding and
 * dispatches {@see EmbedChunksJob}, which re-embeds only that chunk.
 *
 * Idempotent: a snapshot already summarized by the model in use is left
 * alone, so only a model change (the `$model` override) or `$force`
 * re-summarizes it. Unless forced, a summary another snapshot already has
 * for the same `content_hash` and model is copied instead of paying for a
 * provider call.
 * A provider failure ({@see EnrichmentProviderException}) is left to fail the
 * job, which retries under {@see self::backoff()}; a
 * {@see NonRetryableProviderException} (such as a missing API key) fails it
 * on the first attempt. Neither touches the link's extraction status or the
 * snapshot's text.
 */
class SummarizeSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Kept below the database queue's `retry_after` (90s), so a slow
     * provider call is killed before the job could be handed out twice.
     */
    public int $timeout = 60;

    /**
     * A link force-deleted (and its snapshots with it) before the job runs
     * makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  string|null  $model  Summarize with this model instead of the configured default.
     * @param  bool  $force  Re-summarize even when the snapshot already has a summary from the model in use (used by `linkerlee:resummarize --all`).
     */
    public function __construct(public LinkSnapshot $snapshot, public ?string $model = null, public bool $force = false)
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
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FailOnException([NonRetryableProviderException::class])];
    }

    /**
     * Skipped when the snapshot is no longer its live link's latest, and
     * when summaries are off (the `none` driver). An empty summary from the
     * provider is treated the same way: `summary` and `summary_model` are
     * left untouched.
     */
    public function handle(SummaryManager $summaries, Chunker $chunker): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        $provider = $summaries->provider($this->model);

        if ($provider instanceof NullSummaryProvider) {
            return;
        }

        if (! $this->force && $this->snapshot->summary !== null && $this->snapshot->summary_model === $provider->model()) {
            return;
        }

        $summary = ($this->force ? null : $this->reusableSummary($provider->model()))
            ?? trim($provider->summarize($this->snapshot->title ?? '', (string) $this->snapshot->content_text));

        if ($summary === '') {
            return;
        }

        $rewroteHead = DB::transaction(fn (): bool => $this->store($summary, $provider->model(), $chunker));

        if ($rewroteHead) {
            EmbedChunksJob::dispatch($this->snapshot)->onConnection($this->connection);
        }
    }

    /**
     * A summary of the same text by the same model, from another snapshot.
     */
    private function reusableSummary(string $model): ?string
    {
        return LinkSnapshot::query()
            ->where('content_hash', $this->snapshot->content_hash)
            ->whereKeyNot($this->snapshot->id)
            ->where('summary_model', $model)
            ->whereNotNull('summary')
            ->value('summary');
    }

    /**
     * Stores the summary and, when the snapshot already has chunks, rewrites
     * chunk 0 to match it. The link row is locked first, the same lock
     * {@see ChunkSnapshotJob} takes, so a chunk swap in flight either
     * finishes before this reads the chunks or sees the new summary itself.
     *
     * When the snapshot had neither a title nor a summary, its chunk 0 is a
     * body chunk; the body is shifted up one ordinal (keeping its
     * embeddings) and a new chunk 0 inserted instead of overwriting it.
     *
     * @return bool whether chunk 0 now needs embedding
     */
    private function store(string $summary, string $model, Chunker $chunker): bool
    {
        $link = Link::withTrashed()->whereKey($this->snapshot->link_id)->lockForUpdate()->first();

        if ($link === null || $link->trashed() || $link->latest_snapshot_id !== $this->snapshot->id) {
            return false;
        }

        $this->snapshot->refresh();

        $title = $this->snapshot->title ?? '';
        $hadHead = $chunker->head($title, $this->snapshot->summary) !== null;

        $this->snapshot->update([
            'summary' => $summary,
            'summary_model' => $model,
        ]);

        $chunks = ContentChunk::query()->where('link_snapshot_id', $this->snapshot->id);

        if (! $chunks->exists()) {
            return false;
        }

        /** @var array{text: string, token_count: int} $head */
        $head = $chunker->head($title, $summary);
        $row = [...$head, 'embedding' => null, 'embedding_model' => null];

        if ($hadHead) {
            (clone $chunks)->where('ordinal', 0)->update($row);

            return true;
        }

        (clone $chunks)->update(['ordinal' => DB::raw('-ordinal - 1')]);
        (clone $chunks)->update(['ordinal' => DB::raw('-ordinal')]);

        ContentChunk::query()->create([
            ...$row,
            'link_id' => $this->snapshot->link_id,
            'link_snapshot_id' => $this->snapshot->id,
            'ordinal' => 0,
        ]);

        return true;
    }
}
