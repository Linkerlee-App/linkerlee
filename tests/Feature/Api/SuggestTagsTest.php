<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('suggest-tags returns tags whose names appear on the page', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel', 'php', 'rust']);

    Http::fake([
        'https://laravel.com/*' => Http::response(
            '<html><head><title>Laravel — The PHP Framework</title>'
            .'<meta name="description" content="Build modern PHP apps with Laravel">'
            .'</head><body><h1>Welcome</h1></body></html>',
            200
        ),
    ]);

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/suggest-tags', ['link' => 'https://laravel.com/docs'])
        ->assertOk();

    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toContain('laravel');
    expect($names)->toContain('php');
    expect($names)->not->toContain('rust');
});

test('suggest-tags only considers tags owned by the current user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = $owner->createToken('ext', ['create'])->plainTextToken;

    $ownerLink = Link::factory()->create(['user_id' => $owner->id]);
    $ownerLink->attachTags(['mine']);

    $otherLink = Link::factory()->create(['user_id' => $other->id]);
    $otherLink->attachTags(['theirs']);

    Http::fake([
        '*' => Http::response('<html><body><h1>mine theirs</h1></body></html>', 200),
    ]);

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/suggest-tags', ['link' => 'https://example.com'])
        ->assertOk();

    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toContain('mine');
    expect($names)->not->toContain('theirs');
});

test('suggest-tags requires create ability', function () {
    $user = User::factory()->create();
    $token = $user->createToken('read', ['read'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/suggest-tags', ['link' => 'https://example.com'])
        ->assertStatus(403);
});

test('suggest-tags validates link is required', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/suggest-tags', [])
        ->assertStatus(422);
});

test('suggest-tags returns empty array when no tags match', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['ruby', 'kotlin']);

    Http::fake([
        '*' => Http::response('<html><body><h1>nothing related here</h1></body></html>', 200),
    ]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/suggest-tags', ['link' => 'https://example.com'])
        ->assertOk()
        ->assertExactJson([]);
});
