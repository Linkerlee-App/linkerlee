<?php

use App\Models\Link;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
    $this->link = Link::factory()->create(['user_id' => $this->owner->id]);
});

test('a user cannot toggle favorite on another users link', function () {
    $this->actingAs($this->other)
        ->patch(route('links.toggle-favorite', $this->link->id))
        ->assertNotFound();

    expect($this->link->fresh()->is_favorite)->toBeFalse();
});

test('a user cannot rate another users link', function () {
    $this->actingAs($this->other)
        ->patch(route('links.rate', $this->link->id), ['rating' => 5])
        ->assertNotFound();

    expect($this->link->fresh()->rating)->toBeNull();
});

test('a user cannot archive another users link', function () {
    $this->actingAs($this->other)
        ->patch(route('links.archive', $this->link->id))
        ->assertNotFound();

    expect($this->link->fresh()->deleted_at)->toBeNull();
});

test('a user cannot delete another users link', function () {
    $this->actingAs($this->other)
        ->delete(route('links.destroy', $this->link->id))
        ->assertNotFound();

    $this->assertDatabaseHas('links', ['id' => $this->link->id]);
});

test('a user cannot update another users link', function () {
    $this->actingAs($this->other)
        ->put(route('links.update', $this->link->id), [
            'link' => 'https://evil.example.com',
            'title' => 'Hijacked',
            'tags' => [],
            'groups' => [],
        ])
        ->assertForbidden();

    expect($this->link->fresh()->title)->not->toBe('Hijacked');
});
