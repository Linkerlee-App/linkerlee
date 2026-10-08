<?php

use App\Enums\HealthStatus;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('the links index exposes a links health fields', function () {
    $user = User::factory()->create();

    $checkedAt = now()->subHour();
    $changedAt = now()->subDays(2);

    $link = Link::factory()->create([
        'user_id' => $user->id,
        'health_status' => HealthStatus::Redirected,
        'redirect_url' => 'https://example.com/new-location',
        'last_checked_at' => $checkedAt,
        'content_changed_at' => $changedAt,
    ]);

    $this->actingAs($user)
        ->get(route('links.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->where('links.data.0.id', $link->id)
            ->where('links.data.0.health_status', 'redirected')
            ->where('links.data.0.redirect_url', 'https://example.com/new-location')
            ->where('links.data.0.last_checked_at', $link->fresh()->getLastCheckedAtForHumansAttribute(true))
            ->where('links.data.0.content_changed_at', $link->fresh()->getContentChangedAtForHumansAttribute(true)));
});

test('a link never checked exposes null health fields', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('links.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Links/Index')
            ->where('links.data.0.health_status', null)
            ->where('links.data.0.redirect_url', null)
            ->where('links.data.0.last_checked_at', null)
            ->where('links.data.0.content_changed_at', null));
});
