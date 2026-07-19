<?php

use App\Jobs\FetchLinkMetadataJob;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('storing a link dispatches the metadata fetch job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('links.store'), [
            'link' => 'https://example.com/article',
            'title' => 'Some title',
            'tags' => [],
            'groups' => [],
        ])
        ->assertRedirect(route('links.index'));

    Queue::assertPushed(FetchLinkMetadataJob::class);
});

test('updating a link with a new url dispatches the metadata fetch job', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'link' => 'https://example.com/old']);

    Queue::fake();

    $this->actingAs($user)->put(route('links.update', $link->id), [
        'link' => 'https://example.com/new',
        'title' => 'Some title',
        'tags' => [],
        'groups' => [],
    ]);

    Queue::assertPushed(FetchLinkMetadataJob::class);
});

test('the metadata job stores page metadata on the link', function () {
    Http::fake([
        'example.com/*' => Http::response(<<<'HTML'
            <html>
            <head>
                <title>Example Article</title>
                <meta name="description" content="A useful article about Laravel testing.">
                <meta property="og:image" content="/images/preview.png">
                <link rel="icon" href="https://example.com/favicon.png">
            </head>
            <body><h1>Example Article Heading</h1></body>
            </html>
            HTML),
    ]);

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'title' => null,
        'description' => null,
        'link' => 'https://example.com/article',
    ]);

    (new FetchLinkMetadataJob($link))->handle();

    $link->refresh();

    expect($link->title)->toBe('Example Article')
        ->and($link->description)->toBe('A useful article about Laravel testing.')
        ->and($link->favicon_url)->toBe('https://example.com/favicon.png')
        ->and($link->preview_image_url)->toBe('https://example.com/images/preview.png')
        ->and($link->page_text)->toContain('example article heading')
        ->and($link->metadata_fetched_at)->not->toBeNull();
});

test('the metadata job does not overwrite a user-provided title or description', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Fetched Title</title><meta name="description" content="Fetched description."></head><body></body></html>'),
    ]);

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'My own title',
        'description' => 'My own description',
        'link' => 'https://example.com/article',
    ]);

    (new FetchLinkMetadataJob($link))->handle();

    $link->refresh();

    expect($link->title)->toBe('My own title')
        ->and($link->description)->toBe('My own description');
});

test('the metadata job leaves the link untouched when the page cannot be fetched', function () {
    Http::fake([
        '*' => Http::response('', 500),
    ]);

    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'My own title',
        'link' => 'https://example.com/broken',
    ]);

    (new FetchLinkMetadataJob($link))->handle();

    $link->refresh();

    expect($link->title)->toBe('My own title')
        ->and($link->page_text)->toBeNull()
        ->and($link->metadata_fetched_at)->toBeNull();
});
