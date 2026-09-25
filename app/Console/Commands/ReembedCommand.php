<?php

namespace App\Console\Commands;

use App\Enrichment\EmbeddingManager;
use App\Jobs\EmbedChunksJob;
use App\Models\LinkSnapshot;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds embeddings for current snapshots from their existing chunks,
 * with no refetch and no re-chunking.
 *
 * Refuses to dispatch anything when `content_chunks.embedding`'s stored
 * vector dimension does not match the target model's, since a vector
 * column can only ever hold vectors of the size it was created with;
 * printing the migration the operator needs is cheaper than corrupting
 * the column.
 */
class ReembedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkerlee:reembed
        {--model= : Embed with this model instead of the configured default, for this run.}
        {--sync : Run inline instead of queuing, for small backfills.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild chunk embeddings from stored chunks, with no refetch';

    public function handle(EmbeddingManager $embeddings): int
    {
        $model = $this->option('model');
        $provider = $embeddings->provider($model);
        $target = $provider->model();
        $dimensions = $provider->dimensions();

        $columnDimensions = $this->columnDimensions();

        if ($columnDimensions === null) {
            $this->error('content_chunks.embedding column not found — has the content_chunks migration run?');

            return Command::FAILURE;
        }

        if ($columnDimensions !== $dimensions) {
            $this->refuse($columnDimensions, $dimensions);

            return Command::FAILURE;
        }

        $sync = (bool) $this->option('sync');
        $dispatched = 0;

        $this->snapshotsNeedingEmbedding($target)->chunkById(200, function ($snapshots) use (&$dispatched, $model, $sync): void {
            foreach ($snapshots as $snapshot) {
                $sync
                    ? EmbedChunksJob::dispatchSync($snapshot, $model)
                    : EmbedChunksJob::dispatch($snapshot, $model);

                $dispatched++;
            }
        });

        $this->info($sync
            ? sprintf('Embedded %d %s.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots')
            : sprintf('Dispatched %d %s to the enrichment queue.', $dispatched, $dispatched === 1 ? 'snapshot' : 'snapshots'));

        return Command::SUCCESS;
    }

    /**
     * `content_chunks.embedding`'s declared vector dimension, or null when
     * the table or column can't be found (for instance, the content_chunks
     * migration hasn't run yet). For a pgvector column, `atttypmod` equals
     * the dimension directly.
     */
    private function columnDimensions(): ?int
    {
        $row = DB::selectOne(
            "select atttypmod from pg_attribute where attrelid = 'content_chunks'::regclass and attname = 'embedding'"
        );

        return $row === null ? null : (int) $row->atttypmod;
    }

    /**
     * Current snapshots (`links.latest_snapshot_id` on a non-trashed link)
     * with at least one chunk missing an embedding, or embedded by a model
     * other than the target.
     *
     * @return Builder<LinkSnapshot>
     */
    private function snapshotsNeedingEmbedding(string $target): Builder
    {
        return LinkSnapshot::query()
            ->current()
            ->whereExists(function ($query) use ($target): void {
                $query->select(DB::raw(1))
                    ->from('content_chunks')
                    ->whereColumn('content_chunks.link_snapshot_id', 'link_snapshots.id')
                    ->where(fn ($query) => $query
                        ->whereNull('embedding')
                        ->orWhereNull('embedding_model')
                        ->orWhere('embedding_model', '!=', $target));
            });
    }

    /**
     * Prints the error and the full text of the migration the operator
     * needs to create before `linkerlee:reembed` can run.
     */
    private function refuse(int $columnDimensions, int $targetDimensions): void
    {
        $this->error(sprintf(
            'content_chunks.embedding is vector(%d), but the target model produces vector(%d). Dispatching nothing.',
            $columnDimensions,
            $targetDimensions,
        ));

        $this->line('Create a migration to resize the column, for example:');
        $this->newLine();
        $this->line($this->migrationText($columnDimensions, $targetDimensions));
        $this->newLine();
        $this->line('Then run `php artisan migrate`, followed by `php artisan linkerlee:reembed`.');
    }

    private function migrationText(int $previousDimensions, int $dimensions): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\DB;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('content_chunks', function (Blueprint \$table) {
                    \$table->dropColumn('embedding');
                });

                Schema::table('content_chunks', function (Blueprint \$table) {
                    \$table->vector('embedding', {$dimensions})->nullable();
                });

                DB::table('content_chunks')->update(['embedding_model' => null]);
            }

            public function down(): void
            {
                Schema::table('content_chunks', function (Blueprint \$table) {
                    \$table->dropColumn('embedding');
                });

                Schema::table('content_chunks', function (Blueprint \$table) {
                    \$table->vector('embedding', {$previousDimensions})->nullable();
                });
            }
        };

        PHP;
    }
}
