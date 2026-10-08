<?php

use App\Health\CheckOutcome;
use App\Health\CheckSchedule;
use App\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $this->schedule = new CheckSchedule;
});

afterEach(function () {
    Carbon::setTestNow();
});

function linkWithInterval(int $days): Link
{
    return (new Link)->forceFill(['check_interval_days' => $days]);
}

test('an unchanged link doubles its interval, capped at the configured max', function () {
    $intervals = [];
    $interval = 7;

    for ($i = 0; $i < 6; $i++) {
        $result = $this->schedule->next(linkWithInterval($interval), CheckOutcome::Unchanged);
        $interval = $result['check_interval_days'];
        $intervals[] = $interval;
    }

    expect($intervals)->toBe([14, 28, 56, 90, 90, 90]);
});

test('an unchanged link is scheduled for now plus its new interval', function () {
    $result = $this->schedule->next(linkWithInterval(14), CheckOutcome::Unchanged);

    expect($result['check_interval_days'])->toBe(28)
        ->and($result['next_check_at'])->toBeInstanceOf(CarbonImmutable::class)
        ->and($result['next_check_at']->equalTo(now()->addDays(28)))->toBeTrue();
});

test('a changed link resets its interval to the configured initial value', function () {
    $result = $this->schedule->next(linkWithInterval(90), CheckOutcome::Changed);

    expect($result['check_interval_days'])->toBe(config('link_health.initial_interval_days'))
        ->and($result['next_check_at']->equalTo(now()->addDays(config('link_health.initial_interval_days'))))->toBeTrue();
});

test('a failed check keeps the interval and retries after the configured error-retry days', function () {
    $result = $this->schedule->next(linkWithInterval(28), CheckOutcome::Failure);

    expect($result['check_interval_days'])->toBe(28)
        ->and($result['next_check_at']->equalTo(now()->addDays(config('link_health.error_retry_days'))))->toBeTrue();
});

test('a gone link keeps its interval but is rechecked at the configured max interval', function () {
    $result = $this->schedule->next(linkWithInterval(14), CheckOutcome::Gone);

    expect($result['check_interval_days'])->toBe(14)
        ->and($result['next_check_at']->equalTo(now()->addDays(config('link_health.max_interval_days'))))->toBeTrue();
});

test('a suspect link keeps its interval and is rechecked after that same interval', function () {
    $result = $this->schedule->next(linkWithInterval(21), CheckOutcome::Suspect);

    expect($result['check_interval_days'])->toBe(21)
        ->and($result['next_check_at']->equalTo(now()->addDays(21)))->toBeTrue();
});

test('next_check_at is always a CarbonImmutable', function (CheckOutcome $outcome) {
    $result = $this->schedule->next(linkWithInterval(7), $outcome);

    expect($result['next_check_at'])->toBeInstanceOf(CarbonImmutable::class);
})->with(CheckOutcome::cases());
