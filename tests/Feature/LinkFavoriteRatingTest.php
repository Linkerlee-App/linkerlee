<?php

use App\Models\Link;
use App\Models\User;

test('authenticated user can toggle favorite on a link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'is_favorite' => false]);

    $this->actingAs($user)
        ->patch(route('links.toggle-favorite', $link->id))
        ->assertOk()
        ->assertJson(['is_favorite' => true]);

    expect($link->fresh()->is_favorite)->toBeTrue();
});

test('toggling favorite twice returns to unfavorited', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'is_favorite' => true]);

    $this->actingAs($user)
        ->patch(route('links.toggle-favorite', $link->id))
        ->assertOk()
        ->assertJson(['is_favorite' => false]);

    expect($link->fresh()->is_favorite)->toBeFalse();
});

test('authenticated user can rate a link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('links.rate', $link->id), ['rating' => 4])
        ->assertOk()
        ->assertJson(['rating' => 4]);

    expect($link->fresh()->rating)->toBe(4);
});

test('user can clear a link rating', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id, 'rating' => 3]);

    $this->actingAs($user)
        ->patch(route('links.rate', $link->id), ['rating' => null])
        ->assertOk();

    expect($link->fresh()->rating)->toBeNull();
});
