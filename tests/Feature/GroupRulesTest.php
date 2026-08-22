<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;

/**
 * A collection holds the links added to it by hand plus the links its tag
 * rules match, minus anything carrying an excluded tag. These cover the
 * evaluation itself; the editing surface is covered in GroupManagementTest.
 */
function taggedLink(User $user, string ...$tags): Link
{
    $link = Link::factory()->create(['user_id' => $user->id]);

    if ($tags !== []) {
        $link->attachTags($tags);
    }

    return $link;
}

/**
 * A rule may name a tag no link carries yet — "match any: blog, paper" where
 * nothing is tagged `paper` — so the names are resolved, not looked up.
 */
function tagIds(string ...$names): array
{
    return collect($names)
        ->map(fn (string $name) => Tag::findOrCreate($name)->id)
        ->all();
}

function ruleGroup(User $user, array $and = [], array $or = [], array $not = []): Group
{
    return Group::factory()
        ->withTagRules($and, $or, $not)
        ->create(['user_id' => $user->id]);
}

test('a match-all rule gathers only links carrying every tag', function () {
    $user = User::factory()->create();
    $both = taggedLink($user, 'rust', 'blog');
    taggedLink($user, 'rust');

    $group = ruleGroup($user, and: tagIds('rust', 'blog'));

    expect($group->links()->pluck('links.id')->all())->toEqual([$both->id]);
});

test('a match-any rule gathers links carrying either tag', function () {
    $user = User::factory()->create();
    $rust = taggedLink($user, 'rust');
    $blog = taggedLink($user, 'blog');
    taggedLink($user, 'cooking');

    $group = ruleGroup($user, or: tagIds('rust', 'blog'));

    expect($group->links()->pluck('links.id')->sort()->values()->all())
        ->toEqual(collect([$rust->id, $blog->id])->sort()->values()->all());
});

test('match-all and match-any together require all of one and any of the other', function () {
    $user = User::factory()->create();
    $matches = taggedLink($user, 'rust', '2024', 'blog');
    taggedLink($user, 'rust', 'blog');
    taggedLink($user, 'rust', '2024');

    $group = ruleGroup(
        $user,
        and: tagIds('rust', '2024'),
        or: tagIds('blog', 'paper'),
    );

    expect($group->links()->pluck('links.id')->all())->toEqual([$matches->id]);
});

test('an exclude rule drops a link the other rules would have gathered', function () {
    $user = User::factory()->create();
    $kept = taggedLink($user, 'rust');
    taggedLink($user, 'rust', 'archived');

    $group = ruleGroup($user, and: tagIds('rust'), not: tagIds('archived'));

    expect($group->links()->pluck('links.id')->all())->toEqual([$kept->id]);
});

/**
 * The exclusion is a filter over the whole collection, not just the rule-matched
 * half — the old query ANDed it onto one branch only, so a hand-added link with
 * an excluded tag stayed visible.
 */
test('an exclude rule also drops a link that was added by hand', function () {
    $user = User::factory()->create();
    $kept = taggedLink($user, 'reading');
    $excluded = taggedLink($user, 'archived');

    $group = ruleGroup($user, not: tagIds('archived'));
    $kept->groups()->attach($group->id);
    $excluded->groups()->attach($group->id);

    expect($group->links()->pluck('links.id')->all())->toEqual([$kept->id]);
});

test('an exclude rule on its own gathers no links', function () {
    $user = User::factory()->create();
    taggedLink($user, 'rust');
    taggedLink($user, 'archived');

    $group = ruleGroup($user, not: tagIds('archived'));

    expect($group->links()->count())->toBe(0);
});

/**
 * The old query left-joined the membership pivot, so a link in several
 * collections came back once per row and inflated both the listing and the count.
 */
test('a link that is both a hand-added member and a rule match appears once', function () {
    $user = User::factory()->create();
    $link = taggedLink($user, 'rust');
    $otherGroup = Group::factory()->create(['user_id' => $user->id]);

    $group = ruleGroup($user, and: tagIds('rust'));
    $link->groups()->attach([$group->id, $otherGroup->id]);

    expect($group->links()->pluck('links.id')->all())->toEqual([$link->id])
        ->and($group->updateLinksCount())->toBe(1);
});

test('another user link with matching tags is never gathered', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $mine = taggedLink($user, 'rust');
    taggedLink($stranger, 'rust');

    $group = ruleGroup($user, and: tagIds('rust'));

    expect($group->links()->pluck('links.id')->all())->toEqual([$mine->id]);
});

test('a rule naming a tag that no longer exists does not break the collection page', function () {
    $user = User::factory()->create();
    $link = taggedLink($user, 'rust');

    $group = ruleGroup($user, and: tagIds('rust'), or: [999999]);

    $this->actingAs($user)
        ->get(route('groups.show', $group->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SingleGroup/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $link->id));
});

/**
 * `links()` is scoped on the collection's own owner rather than on the logged-in
 * user, because the public share page runs it with nobody logged in at all —
 * where a scope built on `Auth::id()` would have matched every tagged link in
 * the database regardless of who owned it.
 */
test('a rule driven collection is scoped to its owner with nobody logged in', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $mine = taggedLink($user, 'rust');
    taggedLink($stranger, 'rust');

    $group = ruleGroup($user, and: tagIds('rust'));

    expect(auth()->check())->toBeFalse()
        ->and($group->links()->pluck('links.id')->all())->toEqual([$mine->id]);
});
