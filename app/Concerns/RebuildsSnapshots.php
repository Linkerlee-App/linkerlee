<?php

namespace App\Concerns;

use App\Models\LinkSnapshot;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * The shared loop of the enrichment rebuild commands (`linkerlee:resummarize`,
 * `linkerlee:rechunk`, `linkerlee:reembed`), for a {@see Command}.
 *
 * Queued, the work only dispatches jobs. Inline (`--sync`), a snapshot whose
 * work throws is reported and counted as failed and the run carries on, the
 * same way `linkerlee:extract --sync` treats a failing link.
 *
 * @mixin Command
 */
trait RebuildsSnapshots
{
    /**
     * Runs `$work` for every snapshot the query matches, 200 at a time, and
     * prints the summary line: "Dispatched N snapshots to the enrichment
     * queue." queued, or "{$verb} N snapshots (x ok, y failed)." inline.
     *
     * @param  Builder<LinkSnapshot>  $query
     * @param  Closure(LinkSnapshot): void  $work
     */
    protected function rebuildEach(Builder $query, bool $sync, string $verb, Closure $work): void
    {
        $ok = 0;
        $failed = 0;

        $query->chunkById(200, function ($snapshots) use (&$ok, &$failed, $sync, $work): void {
            foreach ($snapshots as $snapshot) {
                if (! $sync) {
                    $work($snapshot);
                    $ok++;

                    continue;
                }

                try {
                    $work($snapshot);
                    $ok++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn("Snapshot {$snapshot->id} failed: {$exception->getMessage()}");
                }
            }
        });

        $total = $ok + $failed;
        $noun = $total === 1 ? 'snapshot' : 'snapshots';

        $this->info($sync
            ? sprintf('%s %d %s (%d ok, %d failed).', $verb, $total, $noun, $ok, $failed)
            : sprintf('Dispatched %d %s to the enrichment queue.', $total, $noun));
    }
}
