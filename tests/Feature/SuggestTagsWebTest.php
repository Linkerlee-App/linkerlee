<?php

use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('a session-authenticated user can fetch tag suggestions', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>A laravel testing guide</title></head><body></body></html>'),
    ]);

    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->syncTags([Tag::findOrCreate('laravel'), Tag::findOrCreate('cooking')]);

    $response = $this->actingAs($user)
        ->postJson(route('suggest-tags'), ['link' => 'https://example.com/article'])
        ->assertOk()
        ->json();

    expect(collect($response)->pluck('name'))->toContain('laravel')
        ->not->toContain('cooking');
});

test('guests cannot fetch tag suggestions', function () {
    $this->postJson(route('suggest-tags'), ['link' => 'https://example.com'])
        ->assertUnauthorized();
});
