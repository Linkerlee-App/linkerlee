<?php

use App\Models\Link;
use App\Models\User;

test('destroy permanently deletes a link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('links.destroy', $link->id))
        ->assertRedirect(route('links.index'));

    $this->assertDatabaseMissing('links', ['id' => $link->id]);
});

test('a trashed link can be permanently deleted', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $this->actingAs($user)
        ->delete(route('links.force-destroy', $link->id))
        ->assertRedirect(route('links.trashed'));

    $this->assertDatabaseMissing('links', ['id' => $link->id]);
});

test('a user cannot permanently delete another users trashed link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $owner->id]);
    $link->delete();

    $this->actingAs($other)
        ->delete(route('links.force-destroy', $link->id))
        ->assertNotFound();

    expect(Link::withTrashed()->find($link->id))->not->toBeNull();
});

test('emptying the trash removes only the current users trashed links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $trashed = Link::factory()->create(['user_id' => $user->id]);
    $trashed->delete();
    $active = Link::factory()->create(['user_id' => $user->id]);
    $otherTrashed = Link::factory()->create(['user_id' => $other->id]);
    $otherTrashed->delete();

    $this->actingAs($user)
        ->delete(route('links.empty-trash'))
        ->assertRedirect(route('links.trashed'));

    $this->assertDatabaseMissing('links', ['id' => $trashed->id]);
    $this->assertDatabaseHas('links', ['id' => $active->id]);
    expect(Link::withTrashed()->find($otherTrashed->id))->not->toBeNull();
});
