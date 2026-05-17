<?php

use App\Models\User;

test('api tokens page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/api-tokens')
        ->assertOk();
});

test('user can create an api token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/settings/api-tokens', ['name' => 'Chrome extension']);

    $response->assertRedirect();
    $response->assertSessionHas('plainTextToken');

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->abilities)->toBe(['create']);
});

test('token name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/api-tokens', [])
        ->assertSessionHasErrors('name');
});

test('user can revoke their own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create']);

    $this->actingAs($user)
        ->delete("/settings/api-tokens/{$token->accessToken->id}")
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

test('user cannot revoke another user token', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $owner->createToken('ext', ['create']);

    $this->actingAs($intruder)
        ->delete("/settings/api-tokens/{$token->accessToken->id}")
        ->assertRedirect();

    expect($owner->tokens()->count())->toBe(1);
});
