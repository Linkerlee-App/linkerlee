<?php

namespace App\Console\Commands;

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
 * model. A snapshot with no chunks yet — one whose earlier chain never
 * reached chunking, for instance after the summary step exhausted its
 * retries — gets the full chain re-run so it recovers without a separate
 * `linkerlee:rechunk`.
 */
class ResummarizeCommand extends Command
{
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
        $target = $summaries->provider($model)->model();
        $force = (bool) $this->option('all');
        $sync = (bool) $this->option('sync');

        $dispatched = 0;

        $this->currentSnapshots()
            ->when(! $force, fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereNull('summary')->orWhere('summary_model', '!=', $target)
            ))
            ->chunkById(200, function ($snapshots) use (&$dispatched, $model, $force, $sync): void {
                foreach ($snapshots as $snapshot) {
                    $this->resummarizeOne($snapshot, $model, $force, $sync);
                    $dispatched++;
                }
            });

        $this->info($sync
            ? sprintf('Resummarized %d %s.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots')
            : sprintf('Dispatched %d %s to the enrichment queue.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots'));

        return Command::SUCCESS;
    }

    /**
     * A snapshot that already has chunks only needs re-summarizing. One
     * with none yet gets the whole chain, so a snapshot stuck mid-pipeline
     * recovers fully instead of staying chunkless forever.
     */
    private function resummarizeOne(LinkSnapshot $snapshot, ?string $model, bool $force, bool $sync): void
    {
        $hasChunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->exists();

        if ($hasChunks) {
            $sync
                ? SummarizeSnapshotJob::dispatchSync($snapshot, $model, $force)
                : SummarizeSnapshotJob::dispatch($snapshot, $model, $force);

            return;
        }

        $chain = Bus::chain([
            new SummarizeSnapshotJob($snapshot, $model, $force),
            new ChunkSnapshotJob($snapshot),
            new EmbedChunksJob($snapshot),
        ]);

        $sync ? $chain->onConnection('sync')->dispatch() : $chain->onQueue('enrichment')->dispatch();
    }

    /**
     * Snapshots referenced by `links.latest_snapshot_id` on a non-trashed
     * link.
     *
     * @return Builder<LinkSnapshot>
     */
    private function currentSnapshots(): Builder
    {
        return LinkSnapshot::query()->whereIn('id', function ($query): void {
            $query->select('latest_snapshot_id')
                ->from('links')
                ->whereNotNull('latest_snapshot_id')
                ->whereNull('deleted_at');
        });
    }
}
