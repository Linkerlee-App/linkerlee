<?php

use App\Models\Link;
use App\Models\User;

test('find returns 404 when no matching link exists', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/links/find?link='.urlencode('https://nothing-here.example'))
        ->assertStatus(404);
});

test('find returns the link with its tags when it exists for the current user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create([
        'user_id' => $user->id,
        'link' => 'https://example.com/post',
        'title' => 'My Post',
    ]);
    $link->attachTags(['php', 'laravel']);

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/links/find?link='.urlencode('https://example.com/post'))
        ->assertOk();

    expect($response->json('id'))->toBe($link->id);
    expect($response->json('title'))->toBe('My Post');
    expect(collect($response->json('tags'))->pluck('name')->all())
        ->toContain('php')
        ->toContain('laravel');
});

test('find does not return links owned by other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $intruder->createToken('ext', ['create'])->plainTextToken;

    Link::factory()->create([
        'user_id' => $owner->id,
        'link' => 'https://private.example/page',
    ]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/links/find?link='.urlencode('https://private.example/page'))
        ->assertStatus(404);
});

test('update replaces title and tags', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create([
        'user_id' => $user->id,
        'link' => 'https://example.com',
        'title' => 'Old',
    ]);
    $link->attachTags(['old-tag']);

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/api/links/{$link->id}", [
            'title' => 'New',
            'newTags' => ['fresh'],
        ])
        ->assertOk();

    expect($response->json('title'))->toBe('New');

    $link->refresh();
    expect($link->title)->toBe('New');
    expect($link->tags->pluck('name')->all())
        ->toContain('fresh')
        ->not->toContain('old-tag');
});

test('update of someone else link returns 404', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $intruder->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/api/links/{$link->id}", ['title' => 'pwned'])
        ->assertStatus(404);
});

test('update requires create ability', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['read'])->plainTextToken;
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/api/links/{$link->id}", ['title' => 'x'])
        ->assertStatus(403);
});

test('passing no tags clears the existing tags on update', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['will-be-removed']);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/api/links/{$link->id}", ['tags' => []])
        ->assertOk();

    expect($link->fresh('tags')->tags->count())->toBe(0);
});
