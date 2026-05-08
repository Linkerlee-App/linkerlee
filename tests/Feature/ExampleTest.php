<?php

use App\Models\User;

test('guests visiting the welcome page see a 200', function () {
    $response = $this->get('/');

    // The public welcome page is served before the auth middleware intercepts
    $response->assertOk();
});

test('authenticated users visiting home are redirected to links', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('links.index'));
});