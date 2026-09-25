<?php

namespace App\Console\Commands;

use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Models\LinkSnapshot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;

/**
 * Rebuilds chunks and embeddings for every current snapshot from stored
 * `content_text` alone, with no refetch.
 *
 * Unlike `linkerlee:resummarize` and `linkerlee:reembed`, this is
 * unconditional: every current snapshot is re-chunked and re-embedded, so
 * this is the command to run after a chunking or embedding config change
 * (chunk size, chars-per-token, embedding model) that the selective
 * commands would not otherwise pick up.
 */
class RechunkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkerlee:rechunk
        {--sync : Run inline instead of queuing, for small backfills.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild chunks and embeddings for current snapshots from stored content_text, with no refetch';

    public function handle(): int
    {
        $sync = (bool) $this->option('sync');

        $dispatched = 0;

        $this->currentSnapshots()->chunkById(200, function ($snapshots) use (&$dispatched, $sync): void {
            foreach ($snapshots as $snapshot) {
                $chain = Bus::chain([
                    new ChunkSnapshotJob($snapshot),
                    new EmbedChunksJob($snapshot),
                ]);

                $sync ? $chain->onConnection('sync')->dispatch() : $chain->onQueue('enrichment')->dispatch();

                $dispatched++;
            }
        });

        $this->info($sync
            ? sprintf('Rechunked %d %s.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots')
            : sprintf('Dispatched %d %s to the enrichment queue.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots'));

        return Command::SUCCESS;
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
