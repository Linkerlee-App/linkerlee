<?php

use App\Enums\LinkSource;
use App\Models\Link;
use App\Models\User;

test('the source filter only shows links captured that way', function () {
    $user = User::factory()->create();
    $fromEmail = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Email]);
    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Web]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['email']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->where('filteredSources', ['email'])
            ->has('links.data', 1)
            ->where('links.data.0.id', $fromEmail->id));
});

test('several sources widen the listing instead of narrowing it', function () {
    $user = User::factory()->create();
    $fromEmail = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Email]);
    $fromExtension = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Extension]);
    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Web]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['email', 'extension']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 2));

    $ids = Link::filterByCurrentUser()->whereIn('source', [LinkSource::Email, LinkSource::Extension])->pluck('id');

    expect($ids->all())->toContain($fromEmail->id, $fromExtension->id);
});

test('links saved before sources were recorded match no source filter', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'source' => null]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['web']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('links.data', 0));
});

test('an unrecognised source is dropped rather than emptying the page', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Web]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['carrier-pigeon']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filteredSources', [])
            ->has('links.data', 1));
});

test('a single source may be sent without array brackets', function () {
    $user = User::factory()->create();
    $fromImport = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Import]);
    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Web]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => 'import']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filteredSources', ['import'])
            ->has('links.data', 1)
            ->where('links.data.0.id', $fromImport->id));
});

test('the source filter combines with a tag filter', function () {
    $user = User::factory()->create();

    $match = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Extension]);
    $match->attachTag('laravel');

    $wrongSource = Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Web]);
    $wrongSource->attachTag('laravel');

    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Extension]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['extension'], 'tags' => ['laravel']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});

test('the source filter does not reach another user links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Link::factory()->create(['user_id' => $other->id, 'source' => LinkSource::Email]);

    $this->actingAs($user)
        ->get(route('links.index', ['source' => ['email']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('links.data', 0));
});

test('the listing exposes the source of each link', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'source' => LinkSource::Extension]);

    $this->actingAs($user)
        ->get(route('links.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('links.data.0.source', 'extension'));
});
