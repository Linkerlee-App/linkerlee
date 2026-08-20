<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\User;

test('destroy soft-deletes the caller own link', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create([
        'user_id' => $user->id,
        'link' => 'https://example.com/remove-me',
    ]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('links', ['id' => $link->id]);
});

test('destroy detaches the tags, as every other delete path in the app does', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['php', 'laravel']);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertNoContent();

    // spatie/laravel-tags detaches on the `deleted` event with no soft-delete
    // guard (HasTags::bootHasTags), so the tags go even though the row is only
    // trashed — a link restored from the trash comes back untagged. That is
    // pre-existing and applies equally to the web app's delete and archive
    // actions; this test pins the behaviour so a change to it is deliberate.
    $trashed = Link::withTrashed()->with('tags')->find($link->id);

    expect($trashed)->not->toBeNull();
    expect($trashed->tags)->toHaveCount(0);
});

test('destroy detaches the link from its groups', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $group = Group::factory()->create(['user_id' => $user->id]);
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->groups()->attach($group->id);

    expect($link->groups()->count())->toBe(1);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertNoContent();

    expect($link->groups()->count())->toBe(0);
});

test('destroy of an unknown link returns 404', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson('/api/links/999999')
        ->assertStatus(404);
});

test('destroy of someone else link returns 404 and leaves it alone', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $intruder->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('links', ['id' => $link->id, 'deleted_at' => null]);
});

test('destroy requires create ability', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['read'])->plainTextToken;
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('links', ['id' => $link->id, 'deleted_at' => null]);
});
