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

test('search returns the user\'s tags by name', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'title' => 'Tagged']);
    $link->attachTag('Laravel');

    $results = $this->actingAs($user)
        ->post(route('search'), ['searchString' => 'laravel'])
        ->assertOk()
        ->json();

    expect(collect($results)->where('type', 'Tags')->pluck('title'))->toContain('Laravel');
});

test('the links index search ignores case', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create(['user_id' => $user->id, 'title' => 'PostgreSQL Internals']);
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Unrelated']);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'postgresql']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});

test('the links index full-text search matches page content when the description is empty', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Untitled bookmark',
        'description' => null,
        'page_text' => 'Notes on Vacuuming and autovacuum tuning',
    ]);
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Unrelated', 'description' => null]);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'autovacuum tuning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});

test('the links index full-text search matches words that are not adjacent', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Untitled bookmark',
        'description' => null,
        'page_text' => 'tuning the planner so that autovacuum keeps up',
    ]);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'autovacuum tuning']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});

test('the generated search vector is never serialised with a link', function () {
    $link = Link::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'Serialised']);

    expect($link->fresh()->getAttributes())->toHaveKey('search_vector')
        ->and($link->fresh()->toArray())->not->toHaveKey('search_vector');
});

test('a link whose text is too large for one tsvector still saves and stays searchable', function () {
    $user = User::factory()->create();
    $words = collect(range(1, 180000))->map(fn (int $i) => 'w'.dechex($i * 7919))->implode(' ');

    $link = Link::factory()->create([
        'user_id' => $user->id,
        'title' => 'Huge page',
        'description' => $words,
    ]);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'Huge']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('links.data.0.id', $link->id));
});

test('a search that only excludes words does not match every link', function (string $search) {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Alpha', 'link' => 'https://a.test/', 'description' => null]);
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Beta', 'link' => 'https://b.test/', 'description' => null]);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => $search]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('links.data', 0));
})->with(['-webkit', '-webkit-appearance', '!!!', '" "']);

test('a search can exclude words from a full-text match', function () {
    $user = User::factory()->create();
    $kept = Link::factory()->create(['user_id' => $user->id, 'title' => 'One', 'description' => null, 'page_text' => 'postgres vacuum']);
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Two', 'description' => null, 'page_text' => 'postgres replication']);

    $this->actingAs($user)
        ->get(route('links.index', ['search' => 'postgres -replication']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $kept->id));
});

test('global search never returns another user\'s tags', function () {
    $owner = User::factory()->create();
    Link::factory()->create(['user_id' => $owner->id])->attachTag('confidential');

    $results = $this->actingAs(User::factory()->create())
        ->post(route('search'), ['searchString' => 'confidential'])
        ->assertOk()
        ->json();

    expect($results)->toBeEmpty();
});
