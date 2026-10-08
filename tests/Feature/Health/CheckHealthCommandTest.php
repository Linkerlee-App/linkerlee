<?php

use App\Enums\ExtractionStatus;
use App\Jobs\CheckLinkHealthJob;
use App\Models\Link;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
});

/**
 * A link ready to be selected by the command: an established extraction
 * status and a `next_check_at` in the past.
 */
function dueLink(User $user, string $url, array $attributes = []): Link
{
    return Link::factory()->create([
        'user_id' => $user->id,
        'link' => $url,
        'extraction_status' => ExtractionStatus::Ok,
        'next_check_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

test('due links are dispatched onto the health queue and links that are not due are skipped', function () {
    Queue::fake();

    $due = dueLink($this->user, 'https://example.com/a');
    dueLink($this->user, 'https://example.org/b', ['next_check_at' => now()->addHour()]);

    $this->artisan('linkerlee:check-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 health checks across 1 hosts.');

    Queue::assertPushedOn('health', CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($due));
    Queue::assertPushedTimes(CheckLinkHealthJob::class, 1);
});

test('three links on the same host are throttled 10 seconds apart, and a different host is not delayed', function () {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());

    $a = dueLink($this->user, 'https://example.com/a', ['next_check_at' => now()->subMinutes(3)]);
    $b = dueLink($this->user, 'https://www.example.com/b', ['next_check_at' => now()->subMinutes(2)]);
    $c = dueLink($this->user, 'https://example.com/c', ['next_check_at' => now()->subMinute()]);
    $other = dueLink($this->user, 'https://another.test/x', ['next_check_at' => now()->subMinutes(3)]);

    $this->artisan('linkerlee:check-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 4 health checks across 2 hosts.');

    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($a) && now()->addSeconds(0)->equalTo($job->delay));
    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($b) && now()->addSeconds(10)->equalTo($job->delay));
    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($c) && now()->addSeconds(20)->equalTo($job->delay));
    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($other) && now()->addSeconds(0)->equalTo($job->delay));
});

test('the batch limit is respected, picking the most overdue links first', function () {
    Queue::fake();
    config(['link_health.batch_size' => 2]);

    $mostOverdue = dueLink($this->user, 'https://a.example/1', ['next_check_at' => now()->subMinutes(3)]);
    $nextOverdue = dueLink($this->user, 'https://b.example/2', ['next_check_at' => now()->subMinutes(2)]);
    dueLink($this->user, 'https://c.example/3', ['next_check_at' => now()->subMinute()]);

    $this->artisan('linkerlee:check-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 2 health checks across 2 hosts.');

    Queue::assertPushedTimes(CheckLinkHealthJob::class, 2);
    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($mostOverdue));
    Queue::assertPushed(CheckLinkHealthJob::class, fn (CheckLinkHealthJob $job) => $job->link->is($nextOverdue));
});

test('trashed and pending links are skipped', function () {
    Queue::fake();

    $trashed = dueLink($this->user, 'https://example.com/trashed');
    $trashed->delete();

    dueLink($this->user, 'https://example.com/pending', ['extraction_status' => ExtractionStatus::Pending]);

    $this->artisan('linkerlee:check-health')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a second immediate run dispatches nothing, and the bump leaves updated_at alone', function () {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());

    $link = dueLink($this->user, 'https://example.com/a');
    $originalUpdatedAt = $link->updated_at;

    $this->artisan('linkerlee:check-health')->assertSuccessful();

    Queue::assertPushedTimes(CheckLinkHealthJob::class, 1);

    $link->refresh();
    expect($link->next_check_at->equalTo(now()->addHour()))->toBeTrue()
        ->and($link->updated_at->equalTo($originalUpdatedAt))->toBeTrue();

    $this->artisan('linkerlee:check-health')->assertSuccessful();

    Queue::assertPushedTimes(CheckLinkHealthJob::class, 1);
});

test('400 links on one host are each bumped past their own delay plus an hour, and none is re-selected while its job waits', function () {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());

    Link::factory()
        ->count(400)
        ->sequence(fn ($sequence) => ['link' => "https://busy.example/{$sequence->index}"])
        ->create([
            'user_id' => $this->user->id,
            'extraction_status' => ExtractionStatus::Ok,
            'next_check_at' => now()->subMinute(),
        ]);

    $this->artisan('linkerlee:check-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 400 health checks across 1 hosts.');

    $jobs = Queue::pushed(CheckLinkHealthJob::class);
    $nextCheckAts = Link::query()->pluck('next_check_at', 'id');

    expect($jobs)->toHaveCount(400);

    foreach ($jobs as $job) {
        $delaySeconds = (int) now()->diffInSeconds($job->delay);

        expect($nextCheckAts[$job->link->id]->greaterThanOrEqualTo(now()->addSeconds($delaySeconds + 3600)))->toBeTrue()
            ->and($job->uniqueFor)->toBeGreaterThanOrEqual($delaySeconds + 3600);
    }

    expect((int) now()->diffInSeconds($jobs->max(fn (CheckLinkHealthJob $job) => $job->delay)))->toBe(3990);

    $this->artisan('linkerlee:check-health')->assertSuccessful();

    Queue::assertPushedTimes(CheckLinkHealthJob::class, 400);

    $this->travel(61)->minutes();

    $this->artisan('linkerlee:check-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 7 health checks across 1 hosts.');
});

test('the command is scheduled hourly', function () {
    $events = app(Schedule::class)->events();

    $event = collect($events)->first(fn ($event) => str_contains($event->command, 'linkerlee:check-health'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});
