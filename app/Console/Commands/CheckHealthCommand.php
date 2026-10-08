<?php

namespace App\Console\Commands;

use App\Enums\ExtractionStatus;
use App\Jobs\CheckLinkHealthJob;
use App\Models\Link;
use Illuminate\Console\Command;

/**
 * Dispatches health checks for links whose `next_check_at` has come due.
 *
 * Runs hourly from the schedule (see `routes/console.php`). The batch is
 * grouped by host so no domain is hit more than once every
 * `link_health.domain_throttle_seconds`: within a group, the i-th link is
 * dispatched with a delay of `i * domain_throttle_seconds`.
 *
 * Before dispatch, every selected link's `next_check_at` is bumped to its own
 * delay plus an hour, so no later hourly run re-selects a link whose delayed
 * job has not run yet; the job overwrites that value once it runs. The bump
 * is one plain query-builder update per distinct delay (at most
 * `link_health.batch_size` of them, one per position within the largest host
 * group), which leaves `updated_at` alone: a health check is not a user edit.
 * The job's unique lock is stretched to the same delay plus an hour, so it
 * too outlives the wait.
 */
class CheckHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkerlee:check-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch health checks for links whose next check is due';

    public function handle(): int
    {
        $links = Link::query()
            ->whereNotNull('next_check_at')
            ->where('next_check_at', '<=', now())
            ->where('extraction_status', '!=', ExtractionStatus::Pending->value)
            ->orderBy('next_check_at')
            ->limit((int) config('link_health.batch_size'))
            ->get();

        $groups = [];

        foreach ($links as $link) {
            $groups[self::hostFor($link->link) ?? 'link:'.$link->id][] = $link;
        }

        $throttleSeconds = (int) config('link_health.domain_throttle_seconds');
        $idsByDelay = [];
        $jobs = [];

        foreach ($groups as $group) {
            foreach (array_values($group) as $position => $link) {
                $delaySeconds = $position * $throttleSeconds;
                $idsByDelay[$delaySeconds][] = $link->id;
                $jobs[] = (new CheckLinkHealthJob($link))->delayedBy($delaySeconds);
            }
        }

        foreach ($idsByDelay as $delaySeconds => $ids) {
            Link::query()->whereKey($ids)->toBase()->update([
                'next_check_at' => now()->addSeconds($delaySeconds + CheckLinkHealthJob::UNIQUE_LOCK_SECONDS),
            ]);
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }

        $this->info(sprintf('Dispatched %d health checks across %d hosts.', count($jobs), count($groups)));

        return self::SUCCESS;
    }

    /**
     * The host a link's URL groups under for throttling: lowercased, with a
     * leading "www." stripped. Null when the URL has no parseable host, so
     * the caller can give it a group of its own.
     */
    private static function hostFor(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
