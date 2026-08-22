<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests can read the privacy policy', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('legal/privacy')
            ->has('canRegister')
        );
});

test('guests can read the terms of service', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('legal/terms')
            ->has('canRegister')
        );
});

test('signed-in users see the same legal pages rather than being redirected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('legal/privacy'));

    $this->actingAs($user)
        ->get(route('legal.terms'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('legal/terms'));
});

test('unverified users can still read the legal pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('legal.privacy'))->assertOk();
    $this->actingAs($user)->get(route('legal.terms'))->assertOk();
});
