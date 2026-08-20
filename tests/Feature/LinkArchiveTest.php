<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('authenticated user can archive a link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('links.archive', $link->id))
        ->assertRedirect(route('links.index'));

    expect(Link::withTrashed()->find($link->id)->trashed())->toBeTrue();
});

test('authenticated user can view trashed links', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $response = $this->actingAs($user)->get(route('links.trashed'));

    $response->assertOk();
});

test('authenticated user can restore an archived link', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $this->actingAs($user)
        ->patch(route('links.restore', $link->id))
        ->assertRedirect(route('links.trashed'));

    expect(Link::find($link->id))->not->toBeNull();
});

test('archived links do not appear in the main index', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->delete();

    $response = $this->actingAs($user)->get(route('links.index'));

    $response->assertOk();
    expect(Link::where('user_id', $user->id)->count())->toBe(0);
});

test('archiving a link keeps its tags, and restoring brings them back', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['keep-me']);

    $link->delete();

    $trashed = Link::withTrashed()->with('tags')->find($link->id);
    expect($trashed->tags->pluck('name')->all())->toContain('keep-me');

    $trashed->restore();
    expect($trashed->fresh('tags')->tags->pluck('name')->all())->toContain('keep-me');
});

test('force deleting a link detaches its tags, leaving no orphaned taggables', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['gone']);

    $link->forceDelete();

    expect(DB::table('taggables')
        ->where('taggable_type', Link::class)
        ->where('taggable_id', $link->id)
        ->count())->toBe(0);
});
