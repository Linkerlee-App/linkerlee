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
 * dispatched with a delay of `i * domain_throttle_seconds`. Every selected
 * link's `next_check_at` is bumped an hour ahead before dispatch, so a slow
 * queue does not make the next hourly run re-select the same link; the job
 * itself overwrites that value once it actually runs.
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

        if ($links->isNotEmpty()) {
            // A plain query-builder update, so `updated_at` is left alone: a
            // health check is not a user edit.
            Link::query()->whereKey($links->pluck('id'))->toBase()->update([
                'next_check_at' => now()->addHour(),
            ]);
        }

        $groups = [];

        foreach ($links as $link) {
            $groups[self::hostFor($link->link) ?? 'link:'.$link->id][] = $link;
        }

        $throttleSeconds = (int) config('link_health.domain_throttle_seconds');
        $dispatched = 0;

        foreach ($groups as $group) {
            foreach (array_values($group) as $position => $link) {
                CheckLinkHealthJob::dispatch($link)
                    ->delay(now()->addSeconds($position * $throttleSeconds));

                $dispatched++;
            }
        }

        $this->info(sprintf('Dispatched %d health checks across %d hosts.', $dispatched, count($groups)));

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
