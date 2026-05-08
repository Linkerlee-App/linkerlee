<?php

use App\Models\Link;
use App\Models\User;

test('authenticated user can archive a link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('links.archive', $link->id))
        ->assertRedirect(route('links.index'));

    expect(Link::withTrashed()->find($link->id)->trashed())->toBeTrue();
});

test('authenticated user can view trashed links', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $response = $this->actingAs($user)->get(route('links.trashed'));

    $response->assertOk();
});

test('authenticated user can restore an archived link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $this->actingAs($user)
        ->patch(route('links.restore', $link->id))
        ->assertRedirect(route('links.trashed'));

    expect(Link::find($link->id))->not->toBeNull();
});

test('archived links do not appear in the main index', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $response = $this->actingAs($user)->get(route('links.index'));

    $response->assertOk();
    expect(Link::where('user_id', $user->id)->count())->toBe(0);
});
