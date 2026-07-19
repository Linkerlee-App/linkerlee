<?php

use App\Models\Link;
use App\Models\User;

test('search matches stored page content', function () {
    $user = User::factory()->create();
    Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Bookmarked article',
        'page_text' => 'a deep dive into eloquent relationships and query scopes',
    ]);

    $results = $this->actingAs($user)
        ->post(route('search'), ['searchString' => 'eloquent'])
        ->assertOk()
        ->json();

    expect(collect($results)->pluck('title'))->toContain('Bookmarked article');
});

test('search matches the link description', function () {
    $user = User::factory()->create();
    Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Some bookmark',
        'description' => 'notes about database indexing strategies',
    ]);

    $results = $this->actingAs($user)
        ->post(route('search'), ['searchString' => 'indexing'])
        ->assertOk()
        ->json();

    expect(collect($results)->pluck('title'))->toContain('Some bookmark');
});

test('search does not return links belonging to other users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Link::factory()->create([
        'user_id' => $owner->id,
        'title' => 'secret roadmap document',
        'page_text' => 'secret roadmap content',
    ]);

    $results = $this->actingAs($other)
        ->post(route('search'), ['searchString' => 'secret'])
        ->assertOk()
        ->json();

    expect($results)->toBeEmpty();
});

test('the links index search matches stored page content', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Untitled bookmark',
        'page_text' => 'exhaustive guide to full-text indexing',
    ]);
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Unrelated']);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'full-text']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});
