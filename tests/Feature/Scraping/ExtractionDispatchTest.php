<?php

use App\Enums\ExtractionStatus;
use App\Events\LinkCreated;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Services\ImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
});

afterEach(function () {
    FakeExtractor::reset();
});

test('storing a link via the web dispatches ExtractContentJob onto the ingestion queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('links.store'), [
            'link' => 'https://example.com/web-created',
            'title' => 'Some title',
            'tags' => [],
            'groups' => [],
        ])
        ->assertRedirect(route('links.index'));

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->link === 'https://example.com/web-created',
    );
});

test('storing a link via the api dispatches ExtractContentJob onto the ingestion queue', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', ['link' => 'https://example.com/api-created'])
        ->assertOk();

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->link === 'https://example.com/api-created',
    );
});

test('mailgun inbound dispatches ExtractContentJob onto the ingestion queue', function () {
    Queue::fake();

    config()->set('services.mailgun.webhook_signing_key', 'test-signing-key');
    config()->set('services.mailgun.inbound_domain', 'mg.linkerlee.com');

    $user = User::factory()->create(['inbox_token' => str_repeat('a', 24)]);

    $timestamp = (string) time();
    $token = 'test-token';
    $signature = hash_hmac('sha256', $timestamp.$token, 'test-signing-key');

    $this->post('/webhooks/mailgun/inbound', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
        'recipient' => 'inbox-'.$user->inbox_token.'@mg.linkerlee.com',
        'sender' => 'someone@example.com',
        'subject' => 'A cool article',
        'body-plain' => 'check out https://laravel.com/mailgun-created today',
    ])->assertOk();

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->link === 'https://laravel.com/mailgun-created',
    );
});

test('importing links dispatches ExtractContentJob once per created link, not per skipped duplicate', function () {
    Queue::fake();

    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'link' => 'https://existing.example']);

    app(ImportService::class)->importUserData([
        'links' => [
            ['title' => 'Existing', 'link' => 'https://existing.example', 'tags' => []],
            ['title' => 'Fresh', 'link' => 'https://fresh.example', 'tags' => []],
        ],
    ], ['links'], $user);

    Queue::assertPushedTimes(ExtractContentJob::class, 1);
    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->link === 'https://fresh.example',
    );
});

test('a web url edit dispatches ExtractContentJob and resets extraction status to pending', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'link' => 'https://example.com/old-url',
        'extraction_status' => ExtractionStatus::Ok,
        'extraction_error' => 'A stale error from a previous attempt',
    ]);

    $this->actingAs($user)->put(route('links.update', $link->id), [
        'link' => 'https://example.com/new-url',
        'title' => 'Some title',
        'tags' => [],
        'groups' => [],
    ]);

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->is($link),
    );

    $link->refresh();
    expect($link->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($link->extraction_error)->toBeNull();
});

test('a web title-only edit does not dispatch ExtractContentJob', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'link' => 'https://example.com/unchanged-url',
        'title' => 'Old title',
        'extraction_status' => ExtractionStatus::Ok,
    ]);

    $this->actingAs($user)->put(route('links.update', $link->id), [
        'link' => 'https://example.com/unchanged-url',
        'title' => 'New title',
        'tags' => [],
        'groups' => [],
    ]);

    Queue::assertNotPushed(ExtractContentJob::class);

    expect($link->fresh()->extraction_status)->toBe(ExtractionStatus::Ok);
});

/**
 * `Queue::fake()` cannot be used here: `QueueFake::push()` records the job
 * immediately and never consults the database transaction manager, so it
 * cannot show deferral. This exercises the real `sync` queue connection
 * (the suite's default) instead, and reads the side effect of the job
 * actually running — a call recorded on {@see FakeExtractor} — to tell
 * "ran" from "still queued".
 */
test('a link created inside a transaction only gets its extraction job dispatched after commit', function () {
    $user = User::factory()->create();

    DB::transaction(function () use ($user) {
        $link = Link::factory()->create([
            'user_id' => $user->id,
            'link' => 'https://example.com/inside-transaction',
        ]);

        LinkCreated::dispatch($link);

        expect(FakeExtractor::$calls)->toBe([]);
    });

    expect(FakeExtractor::$calls)->toBe(['https://example.com/inside-transaction']);
});

test('dispatching from the controller still runs the extraction job under RefreshDatabase', function () {
    Http::fake(['example.com/*' => Http::response('<html><head><title>T</title></head></html>')]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('links.store'), [
            'link' => 'https://example.com/refresh-database-check',
            'title' => 'Some title',
            'tags' => [],
            'groups' => [],
        ])
        ->assertRedirect(route('links.index'));

    expect(FakeExtractor::$calls)->toBe(['https://example.com/refresh-database-check']);
});

test('restoring a trashed link that was never extracted re-queues extraction', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'extraction_status' => ExtractionStatus::Failed,
        'extraction_error' => 'A stale error',
    ]);
    $link->delete();

    $this->actingAs($user)
        ->patch(route('links.restore', $link->id))
        ->assertRedirect(route('links.trashed'));

    $link->refresh();

    expect($link->trashed())->toBeFalse()
        ->and($link->extraction_status)->toBe(ExtractionStatus::Pending)
        ->and($link->extraction_error)->toBeNull();

    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($link));
});

test('restoring a trashed pending link, like an archived import, pushes the job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'extraction_status' => ExtractionStatus::Pending,
    ]);
    $link->delete();

    $this->actingAs($user)->patch(route('links.restore', $link->id));

    Queue::assertPushed(ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($link));
});

test('restoring a trashed link that was already extracted does not re-queue extraction', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'extraction_status' => ExtractionStatus::Ok,
    ]);
    $link->delete();

    $this->actingAs($user)->patch(route('links.restore', $link->id));

    expect($link->fresh()->extraction_status)->toBe(ExtractionStatus::Ok);

    Queue::assertNotPushed(ExtractContentJob::class);
});
