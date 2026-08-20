<?php

use App\Models\Link;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('popular tags carry the name needed to open the filtered links view', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('popularTags', 1)
            ->where('popularTags.0.name', 'laravel')
            ->where('popularTags.0.count', 1));

    $this->actingAs($user)
        ->get(route('links.index', ['tags' => 'laravel']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $link->id));
});
