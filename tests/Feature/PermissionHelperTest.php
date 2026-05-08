<?php

use App\Helpers\PermissionHelper;
use App\Models\Link;
use App\Models\User;

test('returns true when user owns the model', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    expect(PermissionHelper::userIsOwnerOfModal($link, $user->id))->toBeTrue();
});

test('returns false when user does not own the model', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $owner->id]);

    expect(PermissionHelper::userIsOwnerOfModal($link, $other->id))->toBeFalse();
});

test('falls back to authenticated user when no id given', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect(PermissionHelper::userIsOwnerOfModal($link))->toBeTrue();
});
