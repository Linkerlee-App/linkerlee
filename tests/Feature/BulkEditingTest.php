<?php

use App\Models\Link;
use App\Models\User;
use App\Services\BulkEditingService;

test('bulk delete removes multiple links', function () {
    $user = User::factory()->create();
    $links = Link::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    app(BulkEditingService::class)->handleLinkEditingAction(
        'delete',
        $links->pluck('id')->toArray()
    );

    expect(Link::where('user_id', $user->id)->count())->toBe(0);
});

test('bulk delete only affects current users links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $userLink = Link::factory()->create(['user_id' => $user->id]);
    $otherLink = Link::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    app(BulkEditingService::class)->handleLinkEditingAction(
        'delete',
        [$userLink->id, $otherLink->id]
    );

    expect(Link::find($userLink->id))->toBeNull()
        ->and(Link::find($otherLink->id))->not->toBeNull();
});
