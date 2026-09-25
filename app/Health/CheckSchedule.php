<?php

namespace App\Health;

use App\Models\Link;
use Carbon\CarbonImmutable;

/**
 * Decides a link's next health-check interval and timestamp from the
 * outcome of the check that just ran.
 */
class CheckSchedule
{
    /**
     * @return array{check_interval_days: int, next_check_at: CarbonImmutable}
     */
    public function next(Link $link, CheckOutcome $outcome): array
    {
        $currentInterval = (int) $link->check_interval_days;
        $maxInterval = (int) config('link_health.max_interval_days');

        return match ($outcome) {
            CheckOutcome::Unchanged => $this->schedule(
                min($currentInterval * 2, $maxInterval),
            ),
            CheckOutcome::Changed => $this->schedule(
                (int) config('link_health.initial_interval_days'),
            ),
            CheckOutcome::Failure => [
                'check_interval_days' => $currentInterval,
                'next_check_at' => $this->now()->addDays((int) config('link_health.error_retry_days')),
            ],
            CheckOutcome::Gone => [
                'check_interval_days' => $currentInterval,
                'next_check_at' => $this->now()->addDays($maxInterval),
            ],
            CheckOutcome::Suspect => [
                'check_interval_days' => $currentInterval,
                'next_check_at' => $this->now()->addDays($currentInterval),
            ],
        };
    }

    /**
     * @return array{check_interval_days: int, next_check_at: CarbonImmutable}
     */
    private function schedule(int $interval): array
    {
        return [
            'check_interval_days' => $interval,
            'next_check_at' => $this->now()->addDays($interval),
        ];
    }

    private function now(): CarbonImmutable
    {
        return now()->toImmutable();
    }
}
