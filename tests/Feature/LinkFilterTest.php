<?php

use App\Models\Link;
use App\Models\User;

test('the favorites filter only shows favorite links', function () {
    $user = User::factory()->create();
    $favorite = Link::factory()->create(['user_id' => $user->id, 'is_favorite' => true]);
    Link::factory()->create(['user_id' => $user->id, 'is_favorite' => false]);

    $this->actingAs($user)
        ->get(route('links.index', ['favorite' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->where('showFavoritesOnly', true)
            ->has('links.data', 1)
            ->where('links.data.0.id', $favorite->id));
});

test('filters can be combined', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create(['user_id' => $user->id, 'is_favorite' => true]);
    Link::factory()->create(['user_id' => $user->id, 'is_favorite' => true, 'read_at' => now()]);
    Link::factory()->create(['user_id' => $user->id, 'is_favorite' => false]);

    $this->actingAs($user)
        ->get(route('links.index', ['favorite' => 1, 'unreadOnly' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id));
});

test('the inbox exposes tags and groups for triage', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inbox/Index')
            ->has('allTags')
            ->has('allGroups')
            ->has('links.data', 1)
            ->has('links.data.0.tags')
            ->has('links.data.0.group_ids'));
});

test('the tag filter only shows links carrying the tag', function () {
    $user = User::factory()->create();
    $tagged = Link::factory()->create(['user_id' => $user->id]);
    $tagged->attachTags(['laravel']);
    $other = Link::factory()->create(['user_id' => $user->id]);
    $other->attachTags(['cooking']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $tagged->id)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', 'laravel')
            ->has('allTags', 2));
});

test('filtering by several tags shows links matching any of them', function () {
    $user = User::factory()->create();
    $laravelLink = Link::factory()->create(['user_id' => $user->id]);
    $laravelLink->attachTags(['laravel']);
    $cookingLink = Link::factory()->create(['user_id' => $user->id]);
    $cookingLink->attachTags(['cooking']);
    $unrelated = Link::factory()->create(['user_id' => $user->id]);
    $unrelated->attachTags(['gardening']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel,cooking']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 2)
            ->has('filteredTags', 2));
});

test('a tag name that no longer resolves stays visible as an active filter', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => ['deleted-tag']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 0)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', 'deleted-tag')
            ->where('filteredTags.0.id', null));
});

test('a tag name containing a comma survives the round trip', function () {
    $user = User::factory()->create();
    $match = Link::factory()->create(['user_id' => $user->id]);
    $match->attachTags(['coffee, tea']);
    $coffee = Link::factory()->create(['user_id' => $user->id]);
    $coffee->attachTags(['coffee']);
    $tea = Link::factory()->create(['user_id' => $user->id]);
    $tea->attachTags(['tea']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => ['coffee, tea']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $match->id)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', 'coffee, tea'));
});

test('the comma separated tags parameter is still accepted', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);
    Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $link->id));
});

test('surrounding whitespace in a requested tag name is ignored', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel, ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', 'laravel'));
});

test('a tags parameter of zero does not silently disable the filter', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => '0']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 0)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', '0'));
});

test('filtering by another users tag name shows nothing and says so', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id]);
    $theirs = Link::factory()->create(['user_id' => $other->id]);
    $theirs->attachTags(['private-tag']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => ['private-tag']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 0)
            ->has('filteredTags', 1)
            ->where('filteredTags.0.name', 'private-tag'));
});

test('the untagged filter and a tag filter are never applied together', function () {
    $user = User::factory()->create();
    $tagged = Link::factory()->create(['user_id' => $user->id]);
    $tagged->attachTags(['laravel']);
    Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => ['laravel'], 'untaggedOnly' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 0)
            ->where('showUntaggedOnly', true)
            ->has('filteredTags', 1));
});

test('a repeated tag name is only applied once', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel,,laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->has('filteredTags', 1));
});
