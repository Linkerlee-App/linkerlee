<?php

use App\Models\Link;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/links', ['link' => 'https://example.com'])
        ->assertStatus(401);
});

test('token without create ability is forbidden', function () {
    $user = User::factory()->create();
    $token = $user->createToken('read-only', ['read'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', ['link' => 'https://example.com'])
        ->assertStatus(403);
});

test('missing link returns validation error', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', ['title' => 'no link here'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('token with create ability can store a link', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', [
            'link' => 'https://laravel.com',
            'title' => 'Laravel',
        ])
        ->assertOk();

    $link = Link::where('user_id', $user->id)->first();

    expect($link)->not->toBeNull();
    expect($link->title)->toBe('Laravel');
    expect($link->link)->toBe('https://laravel.com');
});

test('newTags create tags and attach them to the link', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', [
            'link' => 'https://example.com',
            'title' => 'Example',
            'newTags' => ['fresh-tag', 'another-one'],
        ])
        ->assertOk();

    $link = Link::where('user_id', $user->id)->first();
    $names = $link->tags->pluck('name')->all();

    expect($names)->toContain('fresh-tag');
    expect($names)->toContain('another-one');
});

test('existing tag ids and newTags can be combined', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $seedLink = Link::factory()->create(['user_id' => $user->id]);
    $seedLink->attachTags(['existing']);
    $existing = $seedLink->tags->first();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', [
            'link' => 'https://example.org',
            'title' => 'Combined',
            'tags' => [$existing->id],
            'newTags' => ['brand-new'],
        ])
        ->assertOk();

    $link = Link::where('user_id', $user->id)->where('link', 'https://example.org')->first();
    $names = $link->tags->pluck('name')->all();

    expect($names)->toContain('existing');
    expect($names)->toContain('brand-new');
});

test('protocol-less link is normalized to https', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', [
            'link' => 'laravel.com',
            'title' => 'Laravel',
        ])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->link)
        ->toBe('https://laravel.com');
});
