<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;

/**
 * The whole collection flow driven through the routes the UI calls, in the
 * order a person meets them: tag some links, build a collection from a rule,
 * narrow it, add a link by hand, then watch the exclusion take that link back
 * out again.
 */
test('a collection gathers links by rule and keeps up as its links change', function () {
    $user = User::factory()->create();

    $rustOnly = Link::factory()->create(['user_id' => $user->id, 'title' => 'Rust book']);
    $rustOnly->attachTags(['rust']);

    $rustArchived = Link::factory()->create(['user_id' => $user->id, 'title' => 'Old rust post']);
    $rustArchived->attachTags(['rust', 'archived']);

    $blog = Link::factory()->create(['user_id' => $user->id, 'title' => 'A blog']);
    $blog->attachTags(['blog']);

    $rust = Tag::findFromString('rust')->id;
    $archived = Tag::findFromString('archived')->id;
    $blogTag = Tag::findFromString('blog')->id;

    // The rule editor counts before anything is saved.
    $this->actingAs($user)
        ->postJson(route('groups.match-count'), [
            'andTags' => [$rust],
            'notTags' => [$archived],
        ])
        ->assertExactJson(['count' => 1]);

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'title' => 'Reading',
            'andTags' => [$rust],
            'notTags' => [$archived],
        ])
        ->assertRedirect(route('groups.index'));

    $group = Group::firstOrFail();

    // The listing reports the count and the rules it was built with.
    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.linksCount', 1)
            ->where('groups.0.andTags.0.name', 'rust')
            ->where('groups.0.notTags.0.name', 'archived'));

    // The collection page lists the gathered link and carries what the rule
    // editor needs to open again.
    $this->actingAs($user)
        ->get(route('groups.show', $group->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SingleGroup/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $rustOnly->id)
            ->where('group.andTags.0.name', 'rust')
            ->has('allTags')
            ->has('allGroups'));

    // Narrowing it with a second rule empties it: nothing is both rust and blog.
    $this->actingAs($user)
        ->put(route('groups.update', $group->id), [
            'title' => 'Reading',
            'andTags' => [$rust],
            'orTags' => [$blogTag],
            'notTags' => [$archived],
        ])
        ->assertRedirect();

    expect($group->fresh()->links_count)->toBe(0);

    // Dropping the rust rule lets the blog link through instead.
    $this->actingAs($user)
        ->put(route('groups.update', $group->id), [
            'title' => 'Reading',
            'orTags' => [$blogTag],
            'notTags' => [$archived],
        ]);

    $this->actingAs($user)
        ->get(route('groups.show', $group->id))
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $blog->id));

    // A link added by hand joins the rule matches, and only once.
    $this->actingAs($user)
        ->put(route('links.update', $rustOnly->id), [
            'link' => $rustOnly->link,
            'title' => $rustOnly->title,
            'tags' => [$rust],
            'groups' => [$group->id],
        ]);

    $this->actingAs($user)
        ->get(route('groups.show', $group->id))
        ->assertInertia(fn ($page) => $page->has('links.data', 2));

    // Tagging that hand-added link with an excluded tag takes it back out —
    // the exclusion covers the whole collection, not just the rule matches.
    $this->actingAs($user)
        ->put(route('links.update', $rustOnly->id), [
            'link' => $rustOnly->link,
            'title' => $rustOnly->title,
            'tags' => [$rust, $archived],
            'groups' => [$group->id],
        ]);

    $this->actingAs($user)
        ->get(route('groups.show', $group->id))
        ->assertInertia(fn ($page) => $page
            ->has('links.data', 1)
            ->where('links.data.0.id', $blog->id));

    expect($group->fresh()->links_count)->toBe(1);
});

/**
 * `links_count` is stored, not derived, so every path that moves a link in or
 * out of a collection has to recompute it or the listing lies.
 */
test('bulk editing keeps the stored collection counts honest', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $group = Group::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('bulk-edit-links'), [
            'action' => 'add_to_groups',
            'links' => [$link->id],
            'groups' => [$group->id],
        ]);

    expect($group->fresh()->links_count)->toBe(1);

    $this->actingAs($user)
        ->post(route('bulk-edit-links'), [
            'action' => 'remove_from_groups',
            'links' => [$link->id],
            'groups' => [$group->id],
        ]);

    expect($group->fresh()->links_count)->toBe(0);
});

test('bulk editing cannot file links into another user collection', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $strangersGroup = Group::factory()->create();

    $this->actingAs($user)
        ->post(route('bulk-edit-links'), [
            'action' => 'add_to_groups',
            'links' => [$link->id],
            'groups' => [$strangersGroup->id],
        ])
        ->assertOk();

    expect($link->fresh()->groupIds())->toBe([]);
});

test('bulk tagging updates the count of a collection whose rule now matches', function () {
    $user = User::factory()->create();

    $tagged = Link::factory()->create(['user_id' => $user->id]);
    $tagged->attachTags(['rust']);
    $rust = Tag::findFromString('rust')->id;

    $group = Group::factory()->withTagRules([$rust])->create(['user_id' => $user->id]);
    $group->updateLinksCount();
    $group->save();

    $untagged = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('bulk-edit-links'), [
            'action' => 'add_tags',
            'links' => [$untagged->id],
            'tags' => [$rust],
        ]);

    expect($group->fresh()->links_count)->toBe(2);
});

/**
 * A sub-collection used to vanish from the listing entirely — only root rows
 * were rendered — so the listing has to carry the parent link that places it.
 */
test('a sub-collection is listed under its parent', function () {
    $user = User::factory()->create();
    $parent = Group::factory()->create(['user_id' => $user->id, 'title' => 'Reading']);

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'title' => 'Rust',
            'parentGroupId' => $parent->id,
        ])
        ->assertRedirect(route('groups.index'));

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertInertia(fn ($page) => $page
            ->has('groups', 2)
            ->where('groups.0.title', 'Reading')
            ->where('groups.0.childGroupsCount', 1)
            ->where('groups.1.title', 'Rust')
            ->where('groups.1.parentGroupId', $parent->id));
});
