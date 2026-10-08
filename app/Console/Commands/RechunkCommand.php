<?php

namespace App\Console\Commands;

use App\Concerns\RebuildsSnapshots;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Models\LinkSnapshot;
use Illuminate\Console\Command;
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
    use RebuildsSnapshots;

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

        $this->rebuildEach(LinkSnapshot::query()->current(), $sync, 'Rechunked', function (LinkSnapshot $snapshot) use ($sync): void {
            $chain = Bus::chain([
                new ChunkSnapshotJob($snapshot),
                new EmbedChunksJob($snapshot),
            ]);

            $sync ? $chain->onConnection('sync')->dispatch() : $chain->onQueue('enrichment')->dispatch();
        });

        return Command::SUCCESS;
    }
}
