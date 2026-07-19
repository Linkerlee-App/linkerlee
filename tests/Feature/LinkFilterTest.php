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
