<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
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

test('a long url is stored in full', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $longUrl = 'https://example.com/article?fbclid='.str_repeat('a', 1900);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', ['link' => $longUrl])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->link)->toBe($longUrl);
});

test('a url longer than the column allows is rejected with a validation error', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', ['link' => 'https://example.com/?q='.str_repeat('a', 2100)])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Link::where('user_id', $user->id)->count())->toBe(0);
});

test('a title longer than the column allows is truncated instead of failing', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $longTitle = str_repeat('Tranzacție în asigurări ', 20);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->post('/api/links', ['link' => 'https://example.com/long-title', 'title' => $longTitle])
        ->assertOk();

    $link = Link::where('user_id', $user->id)->first();

    expect(mb_strlen($link->title))->toBe(255)
        ->and($link->title)->toBe(mb_substr($longTitle, 0, 255));
});

/**
 * A collection's tag rules gather links by their tags, so a link the extension
 * creates or retags changes what the collections listing should be counting —
 * even though the API never names a collection.
 */
test('tagging a link through the api keeps collection counts current', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create', 'update'])->plainTextToken;

    $seed = Link::factory()->create(['user_id' => $user->id]);
    $seed->attachTags(['rust']);
    $rust = Tag::findFromString('rust')->id;

    $group = Group::factory()
        ->withTagRules([$rust])
        ->create(['user_id' => $user->id]);
    $group->updateLinksCount();
    $group->save();

    expect($group->fresh()->links_count)->toBe(1);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', [
            'link' => 'https://example.com/rust-post',
            'tags' => [$rust],
        ])
        ->assertSuccessful();

    expect($group->fresh()->links_count)->toBe(2);

    $untagged = Link::factory()->create(['user_id' => $user->id]);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->putJson("/api/links/{$untagged->id}", ['tags' => [$rust]])
        ->assertSuccessful();

    expect($group->fresh()->links_count)->toBe(3);
});
