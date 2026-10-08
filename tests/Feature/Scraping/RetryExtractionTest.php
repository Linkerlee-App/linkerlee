<?php

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
});

afterEach(function () {
    FakeExtractor::reset();
});

test('the owner can retry extraction and the job is pushed', function () {
    Queue::fake();

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'extraction_status' => ExtractionStatus::Failed,
        'extraction_error' => 'Some stale error',
    ]);

    $response = $this->actingAs($user)
        ->patch(route('links.retry-extraction', $link->id))
        ->assertOk();

    $response->assertJson(['extraction_status' => 'pending']);

    $link->refresh();
    expect($link->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($link->extraction_error)->toBeNull();

    Queue::assertPushedOn(
        'ingestion',
        ExtractContentJob::class,
        fn (ExtractContentJob $job) => $job->link->is($link),
    );
});

test('another user cannot retry extraction on a link they do not own', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $owner->id,
        'extraction_status' => ExtractionStatus::Failed,
    ]);

    $this->actingAs($other)
        ->patch(route('links.retry-extraction', $link->id))
        ->assertNotFound();

    expect($link->fresh()->extraction_status)->toBe(ExtractionStatus::Failed);

    Queue::assertNotPushed(ExtractContentJob::class);
});

test('the link resource exposes extraction_status', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'extraction_status' => ExtractionStatus::Blocked,
    ]);

    $this->actingAs($user)
        ->get(route('links.show', $link->id))
        ->assertInertia(fn ($page) => $page
            ->where('link.extraction_status', 'blocked'));
});
