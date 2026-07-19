<?php

use App\Models\Link;
use App\Models\User;

test('authenticated user can mark a link as read', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('links.toggle-read', $link->id))
        ->assertOk();

    expect($link->fresh()->read_at)->not->toBeNull();
});

test('toggling read twice marks the link unread again', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'read_at' => now()]);

    $this->actingAs($user)
        ->patch(route('links.toggle-read', $link->id))
        ->assertOk()
        ->assertJson(['read_at' => null]);

    expect($link->fresh()->read_at)->toBeNull();
});

test('user cannot toggle read status on another users link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->patch(route('links.toggle-read', $link->id))
        ->assertNotFound();

    expect($link->fresh()->read_at)->toBeNull();
});

test('the unread filter only shows unread links', function () {
    $user = User::factory()->create();
    $unread = Link::factory()->create(['user_id' => $user->id]);
    Link::factory()->create(['user_id' => $user->id, 'read_at' => now()]);

    $this->actingAs($user)
        ->get(route('links.index', ['unreadOnly' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->where('showUnreadOnly', true)
            ->has('links.data', 1)
            ->where('links.data.0.id', $unread->id));
});
