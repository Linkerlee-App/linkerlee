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

test('destroy keeps the tags so a restored link comes back intact', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['php', 'laravel']);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->deleteJson("/api/links/{$link->id}")
        ->assertNoContent();

    $trashed = Link::withTrashed()->with('tags')->find($link->id);

    expect($trashed->tags->pluck('name')->all())
        ->toContain('php')
        ->toContain('laravel');

    $trashed->restore();

    expect($trashed->fresh('tags')->tags->pluck('name')->all())
        ->toContain('php')
        ->toContain('laravel');
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
