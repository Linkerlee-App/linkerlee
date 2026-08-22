<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;

/**
 * Creating, editing and deleting a collection, including the rule editor's
 * live match count. The rules' evaluation is covered in GroupRulesTest.
 */
function userWithTag(string $name, ?User $user = null): array
{
    $user ??= User::factory()->create();

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags([$name]);

    return [$user, Tag::findFromString($name)->id, $link];
}

test('creating a collection stores its tag rules', function () {
    [$user, $rust] = userWithTag('rust');
    [, $archived] = userWithTag('archived', $user);

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'title' => 'Reading',
            'andTags' => [$rust],
            'notTags' => [$archived],
        ])
        ->assertRedirect(route('groups.index'));

    expect(Group::firstOrFail()->query_options)->toEqual([
        'containsTagsAnd' => [$rust],
        'containsTagsNot' => [$archived],
    ]);
});

/**
 * `links_count` used to be computed before the row was saved, so a brand new
 * collection had no id to match its members against and always reported zero.
 */
test('a new collection reports the number of links its rules gather', function () {
    [$user, $rust] = userWithTag('rust');

    $this->actingAs($user)
        ->post(route('groups.store'), ['title' => 'Reading', 'andTags' => [$rust]]);

    expect(Group::firstOrFail()->links_count)->toBe(1);
});

test('editing a collection changes and clears its tag rules', function () {
    [$user, $rust] = userWithTag('rust');
    [, $blog] = userWithTag('blog', $user);

    $group = Group::factory()->withTagRules([$rust])->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('groups.update', $group->id), [
            'title' => 'Reading',
            'orTags' => [$rust, $blog],
        ])
        ->assertRedirect();

    expect($group->fresh()->query_options)->toEqual(['containsTagsOr' => [$rust, $blog]]);

    $this->actingAs($user)
        ->put(route('groups.update', $group->id), ['title' => 'Reading']);

    expect($group->fresh()->query_options)->toEqual([]);
});

test('a collection title is required and rules are not', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), ['title' => ''])
        ->assertSessionHasErrors('title');

    $this->actingAs($user)
        ->post(route('groups.store'), ['title' => 'Reading'])
        ->assertSessionHasNoErrors();
});

test('a rule cannot name another user tag', function () {
    $user = User::factory()->create();
    [, $strangersTag] = userWithTag('rust');

    $this->actingAs($user)
        ->post(route('groups.store'), ['title' => 'Reading', 'andTags' => [$strangersTag]])
        ->assertSessionHasErrors('andTags.0');
});

test('a collection cannot be nested under another user collection', function () {
    $user = User::factory()->create();
    $strangersGroup = Group::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'title' => 'Reading',
            'parentGroupId' => $strangersGroup->id,
        ])
        ->assertSessionHasErrors('parentGroupId');
});

test('a collection cannot be nested inside itself', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('groups.update', $group->id), [
            'title' => 'Reading',
            'parentGroupId' => $group->id,
        ])
        ->assertSessionHasErrors('parentGroupId');
});

/**
 * A→B→A would make `getAllParentGroups()` walk forever.
 */
test('a collection cannot be nested under its own descendant', function () {
    $user = User::factory()->create();
    $parent = Group::factory()->create(['user_id' => $user->id]);
    $child = Group::factory()->create(['user_id' => $user->id, 'parent_group_id' => $parent->id]);
    $grandchild = Group::factory()->create(['user_id' => $user->id, 'parent_group_id' => $child->id]);

    $this->actingAs($user)
        ->put(route('groups.update', $parent->id), [
            'title' => 'Reading',
            'parentGroupId' => $grandchild->id,
        ])
        ->assertSessionHasErrors('parentGroupId');
});

test('another user collection cannot be edited or deleted', function () {
    $user = User::factory()->create();
    $strangersGroup = Group::factory()->create();

    $this->actingAs($user)
        ->put(route('groups.update', $strangersGroup->id), ['title' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('groups.destroy', $strangersGroup->id))
        ->assertNotFound();

    expect(Group::whereKey($strangersGroup->id)->exists())->toBeTrue();
});

test('deleting your own collection removes it', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('groups.destroy', $group->id))
        ->assertRedirect(route('groups.index'));

    expect(Group::whereKey($group->id)->exists())->toBeFalse();
});

test('the collections listing sends the rule tags and every tag to pick from', function () {
    [$user, $rust] = userWithTag('rust');
    Group::factory()->withTagRules([$rust])->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Groups/Index')
            ->where('groups.0.andTags', [['id' => $rust, 'name' => 'rust']])
            ->where('groups.0.orTags', [])
            ->has('allTags', 1));
});

test('the match count endpoint agrees with the saved collection', function () {
    [$user, $rust] = userWithTag('rust');
    [, $archived, $archivedLink] = userWithTag('archived', $user);
    $archivedLink->attachTags(['rust']);

    $this->actingAs($user)
        ->postJson(route('groups.match-count'), [
            'andTags' => [$rust],
            'notTags' => [$archived],
        ])
        ->assertOk()
        ->assertExactJson(['count' => 1]);
});

/**
 * While editing, the links already added to the collection by hand count
 * towards the total the dialog reports.
 */
test('the match count includes a collection existing members when editing it', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create(['user_id' => $user->id]);
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->groups()->attach($group->id);

    $this->actingAs($user)
        ->postJson(route('groups.match-count'), ['groupId' => $group->id])
        ->assertOk()
        ->assertExactJson(['count' => 1]);

    $this->actingAs($user)
        ->postJson(route('groups.match-count'), [])
        ->assertOk()
        ->assertExactJson(['count' => 0]);
});

/**
 * A share page lists the collection owner's links, so minting one for someone
 * else's collection would publish their links to anyone holding the URL.
 */
test('a share page cannot be created for another user collection', function () {
    $user = User::factory()->create();
    $strangersGroup = Group::factory()->create();

    $this->actingAs($user)
        ->post(route('publicLinks.store'), ['groupId' => $strangersGroup->id])
        ->assertSessionHasErrors('groupId');

    expect($strangersGroup->publicLink()->exists())->toBeFalse();
});
