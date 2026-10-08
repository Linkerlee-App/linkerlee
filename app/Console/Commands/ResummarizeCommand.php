<?php

namespace App\Console\Commands;

use App\Concerns\RebuildsSnapshots;
use App\Enrichment\NonRetryableProviderException;
use App\Enrichment\Providers\AnthropicSummaryProvider;
use App\Enrichment\Providers\NullSummaryProvider;
use App\Enrichment\SummaryManager;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;

/**
 * Rebuilds snapshot summaries from stored `content_text` alone, with no
 * refetch.
 *
 * Operates only on current snapshots (`links.latest_snapshot_id` on a
 * non-trashed link). Without `--all` it targets a snapshot with no summary
 * yet, or one summarized by a different model than the target; `--all`
 * forces every current snapshot, even one already matching the target
 * model. A snapshot with no chunks yet — one whose chunk-then-embed chain
 * never ran — also gets that chain, so it recovers without a separate
 * `linkerlee:rechunk`.
 *
 * Does nothing under the `none` summary driver, and refuses to start when
 * the `anthropic` driver has no API key, rather than queuing a job per
 * snapshot that could only fail.
 */
class ResummarizeCommand extends Command
{
    use RebuildsSnapshots;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkerlee:resummarize
        {--model= : Summarize with this model instead of the configured default, for this run.}
        {--all : Re-summarize every current snapshot, even one already summarized by the target model.}
        {--sync : Run inline instead of queuing, for small backfills.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild snapshot summaries from stored content_text, with no refetch';

    public function handle(SummaryManager $summaries): int
    {
        $model = $this->option('model');
        $provider = $summaries->provider($model);

        if ($provider instanceof NullSummaryProvider) {
            $this->info('Summaries are disabled (SUMMARY_DRIVER=none). Dispatching nothing.');

            return Command::SUCCESS;
        }

        if ($provider instanceof AnthropicSummaryProvider) {
            try {
                $provider->assertConfigured();
            } catch (NonRetryableProviderException $exception) {
                $this->error("{$exception->getMessage()} Dispatching nothing.");

                return Command::FAILURE;
            }
        }

        $target = $provider->model();
        $includeAll = (bool) $this->option('all');
        $sync = (bool) $this->option('sync');

        $query = LinkSnapshot::query()
            ->current()
            ->when(! $includeAll, fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereNull('summary')->orWhere('summary_model', '!=', $target)
            ));

        $this->rebuildEach($query, $sync, 'Resummarized', fn (LinkSnapshot $snapshot) => $this->resummarizeOne($snapshot, $model, $includeAll, $sync));

        return Command::SUCCESS;
    }

    /**
     * A snapshot that already has chunks only needs re-summarizing: the job
     * rewrites and re-embeds chunk 0 itself. One with none yet also gets the
     * chunk-then-embed chain, dispatched independently of the summary so a
     * summary failure never keeps it chunkless. Inline, the summary runs
     * first so chunk 0 is cut with it, and the chain runs even when the
     * summary throws; the exception is then rethrown to the caller.
     *
     * `$includeAll` (this run's `--all`) is passed through as the job's own
     * `force`, so a snapshot already matching the target model is
     * re-summarized anyway instead of being skipped by the job itself.
     */
    private function resummarizeOne(LinkSnapshot $snapshot, ?string $model, bool $includeAll, bool $sync): void
    {
        $summarize = new SummarizeSnapshotJob($snapshot, $model, force: $includeAll);

        if (ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->exists()) {
            $sync ? dispatch_sync($summarize) : dispatch($summarize);

            return;
        }

        $chain = Bus::chain([
            new ChunkSnapshotJob($snapshot),
            new EmbedChunksJob($snapshot),
        ]);

        if (! $sync) {
            dispatch($summarize);
            $chain->onQueue('enrichment')->dispatch();

            return;
        }

        try {
            dispatch_sync($summarize);
        } finally {
            $chain->onConnection('sync')->dispatch();
        }
    }
}
