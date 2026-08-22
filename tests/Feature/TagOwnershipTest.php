<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tag rows carry no owner column and are shared: two people tagging a link
 * "rust" share one row, and `taggables.tag_id` cascades on delete. So a tag is
 * reachable only by someone filed under it, and editing or deleting one must
 * not reach across to anybody else's links.
 */
function linkTagged(User $user, string ...$names): Link
{
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags($names);

    return $link;
}

test('another user tag cannot be deleted', function () {
    $victim = User::factory()->create();
    $victimsLink = linkTagged($victim, 'rust');
    $tagId = Tag::findFromString('rust')->id;

    $attacker = User::factory()->create();

    $this->actingAs($attacker)
        ->delete(route('tags.destroy', $tagId))
        ->assertNotFound();

    expect(Tag::whereKey($tagId)->exists())->toBeTrue()
        ->and($victimsLink->fresh()->tags->pluck('name')->all())->toEqual(['rust']);
});

test('another user tag cannot be renamed', function () {
    $victim = User::factory()->create();
    $victimsLink = linkTagged($victim, 'rust');
    $tagId = Tag::findFromString('rust')->id;

    $attacker = User::factory()->create();

    $this->actingAs($attacker)
        ->put(route('tags.update', $tagId), ['tagName' => 'hijacked'])
        ->assertNotFound();

    expect($victimsLink->fresh()->tags->pluck('name')->all())->toEqual(['rust']);
});

test('deleting your own tag removes it from your links', function () {
    $user = User::factory()->create();
    $link = linkTagged($user, 'rust', 'keepme');
    $tagId = Tag::findFromString('rust')->id;

    $this->actingAs($user)
        ->delete(route('tags.destroy', $tagId))
        ->assertRedirect();

    expect($link->fresh()->tags->pluck('name')->all())->toEqual(['keepme'])
        ->and(Tag::whereKey($tagId)->exists())->toBeFalse();
});

/**
 * The row is shared, so one user letting go of it must not take it away from
 * everyone else — it only disappears once nothing points at it.
 */
test('deleting a tag someone else also uses leaves their links alone', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $myLink = linkTagged($mine, 'rust');
    $tagId = Tag::findFromString('rust')->id;
    $theirLink = linkTagged($theirs, 'rust');

    $this->actingAs($mine)
        ->delete(route('tags.destroy', $tagId))
        ->assertRedirect();

    expect($myLink->fresh()->tags)->toHaveCount(0)
        ->and($theirLink->fresh()->tags->pluck('name')->all())->toEqual(['rust'])
        ->and(Tag::whereKey($tagId)->exists())->toBeTrue();
});

/**
 * Archiving keeps a link's tags so restoring brings it back intact, so a tag
 * dropped from the library has to leave the archived links too — otherwise it
 * reappears the moment one is restored.
 */
test('deleting a tag also clears it from your archived links', function () {
    $user = User::factory()->create();
    $live = linkTagged($user, 'rust');
    $archived = linkTagged($user, 'rust');
    $tagId = Tag::findFromString('rust')->id;
    $archived->delete();

    $this->actingAs($user)
        ->delete(route('tags.destroy', $tagId))
        ->assertRedirect();

    expect($live->fresh()->tags)->toHaveCount(0)
        ->and(Link::withTrashed()->find($archived->id)->tags)->toHaveCount(0)
        ->and(Tag::whereKey($tagId)->exists())->toBeFalse();
});

/**
 * A tag surviving only on archived links is not listed on the tags page, so it
 * is not actionable there either — the guard and the listing agree.
 */
test('a tag left only on archived links is not reachable', function () {
    $user = User::factory()->create();
    $archived = linkTagged($user, 'rust');
    $tagId = Tag::findFromString('rust')->id;
    $archived->delete();

    $this->actingAs($user)
        ->delete(route('tags.destroy', $tagId))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertInertia(fn ($page) => $page->has('tags', 0));
});

test('renaming a tag only you use renames it in place', function () {
    $user = User::factory()->create();
    $link = linkTagged($user, 'rust');
    $tagId = Tag::findFromString('rust')->id;

    $this->actingAs($user)
        ->put(route('tags.update', $tagId), ['tagName' => 'rustlang'])
        ->assertRedirect();

    expect($link->fresh()->tags->pluck('name')->all())->toEqual(['rustlang'])
        ->and(Tag::whereKey($tagId)->first()->name)->toBe('rustlang');
});

/**
 * The shared row cannot simply be renamed, or it would be renamed under
 * everyone; the current user's links move to a tag of the new name instead.
 */
test('renaming a tag someone else uses moves only your links', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $myLink = linkTagged($mine, 'rust');
    $tagId = Tag::findFromString('rust')->id;
    $theirLink = linkTagged($theirs, 'rust');

    $this->actingAs($mine)
        ->put(route('tags.update', $tagId), ['tagName' => 'rustlang'])
        ->assertRedirect();

    expect($myLink->fresh()->tags->pluck('name')->all())->toEqual(['rustlang'])
        ->and($theirLink->fresh()->tags->pluck('name')->all())->toEqual(['rust']);
});

/**
 * A collection rule naming the tag has to follow the links it was matching,
 * or the collection quietly empties after a rename.
 */
test('renaming a shared tag repoints your collection rules at it', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $myLink = linkTagged($mine, 'rust');
    $tagId = Tag::findFromString('rust')->id;
    linkTagged($theirs, 'rust');

    $group = Group::factory()->withTagRules([$tagId])->create(['user_id' => $mine->id]);

    $this->actingAs($mine)
        ->put(route('tags.update', $tagId), ['tagName' => 'rustlang']);

    $newTagId = Tag::findFromString('rustlang')->id;

    expect($group->fresh()->query_options)->toEqual(['containsTagsAnd' => [$newTagId]])
        ->and($group->fresh()->links()->pluck('links.id')->all())->toEqual([$myLink->id]);
});

test('creating a tag does not mint a duplicate row', function () {
    $user = User::factory()->create();
    linkTagged($user, 'rust');

    $this->actingAs($user)
        ->post(route('tags.store'), ['tagName' => 'rust'])
        ->assertRedirect();

    expect(DB::table('tags')->count())->toBe(1);
});
