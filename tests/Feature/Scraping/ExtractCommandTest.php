<?php

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();

    $this->user = User::factory()->create();
});

afterEach(function () {
    FakeExtractor::reset();
});

test('with no options, pending links are dispatched and ok links are left alone', function () {
    Queue::fake();

    $pending = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Ok,
    ]);

    $this->artisan('linkerlee:extract')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 link to the ingestion queue.');

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->is($pending),
    );
    Queue::assertPushedTimes(ExtractContentJob::class, 1);
});

test('--failed selects only failed and blocked links', function () {
    Queue::fake();

    $failed = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Failed,
    ]);
    $blocked = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Blocked,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Ok,
    ]);

    $this->artisan('linkerlee:extract', ['--failed' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 2 links to the ingestion queue.');

    Queue::assertPushedTimes(ExtractContentJob::class, 2);
    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($failed));
    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($blocked));
});

test('--link selects exactly one link regardless of its status', function () {
    Queue::fake();

    $ok = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Ok,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);

    $this->artisan('linkerlee:extract', ['--link' => $ok->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 link to the ingestion queue.');

    Queue::assertPushedTimes(ExtractContentJob::class, 1);
    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($ok));
});

test('--link wins over --failed when both are given', function () {
    Queue::fake();

    $target = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Ok,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Failed,
    ]);

    $this->artisan('linkerlee:extract', ['--failed' => true, '--link' => $target->id])
        ->assertSuccessful();

    Queue::assertPushedTimes(ExtractContentJob::class, 1);
    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($target));
});

test('an unknown --link id fails with an error', function () {
    Queue::fake();

    $this->artisan('linkerlee:extract', ['--link' => 999999])
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('a trashed --link id fails with an error', function () {
    Queue::fake();

    $trashed = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    $trashed->delete();

    $this->artisan('linkerlee:extract', ['--link' => $trashed->id])
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('trashed links are excluded from the default and --failed selections', function () {
    Queue::fake();

    $trashedPending = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    $trashedPending->delete();

    $trashedFailed = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Failed,
    ]);
    $trashedFailed->delete();

    $this->artisan('linkerlee:extract')->assertSuccessful();
    $this->artisan('linkerlee:extract', ['--failed' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

test('--sync runs extraction inline and creates snapshots', function () {
    FakeExtractor::respondWith('*', ExtractionResult::ok('fake', 'Some article body text'));

    $link = Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);

    $this->artisan('linkerlee:extract', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Extracted 1 link (1 ok, 0 failed).');

    expect($link->fresh()->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('--sync counts a permanent failure without aborting the backfill', function () {
    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Unsupported, 'fake', 'Not HTML'));

    Link::factory()->create([
        'user_id' => $this->user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);

    $this->artisan('linkerlee:extract', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Extracted 1 link (0 ok, 1 failed).');
});

test('--sync counts a transient failure as failed and keeps processing the rest', function () {
    FakeExtractor::respondWith(
        '*',
        fn (string $url): ExtractionResult => str_contains($url, 'transient')
            ? ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'HTTP 503', ['transient' => true])
            : ExtractionResult::ok('fake', 'Some article body text'),
    );

    Link::factory()->create([
        'user_id' => $this->user->id,
        'link' => 'https://example.com/transient',
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    Link::factory()->create([
        'user_id' => $this->user->id,
        'link' => 'https://example.com/fine',
        'extraction_status' => ExtractionStatus::Pending,
    ]);

    $this->artisan('linkerlee:extract', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Extracted 2 links (1 ok, 1 failed).');
});
