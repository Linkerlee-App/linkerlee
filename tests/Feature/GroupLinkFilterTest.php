<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\User;

/**
 * The collection listing filters through the same scope as the links view,
 * so the tag filter has to narrow — and normalise its names — identically.
 */
function groupWithLinks(User $user, Link ...$links): Group
{
    $group = Group::factory()->create(['user_id' => $user->id]);

    foreach ($links as $link) {
        $link->groups()->attach($group->id);
    }

    return $group;
}

test('filtering a collection by several tags only shows links carrying all of them', function () {
    $user = User::factory()->create();
    $both = Link::factory()->create(['user_id' => $user->id]);
    $both->attachTags(['laravel', 'cooking']);
    $laravelOnly = Link::factory()->create(['user_id' => $user->id]);
    $laravelOnly->attachTags(['laravel']);

    $group = groupWithLinks($user, $both, $laravelOnly);

    $this->actingAs($user)
        ->get(route('groups.show', ['group' => $group->id, 'tags' => 'laravel,cooking']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SingleGroup/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $both->id));
});

test('a trailing comma does not empty a filtered collection', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['laravel']);

    $group = groupWithLinks($user, $link);

    $this->actingAs($user)
        ->get(route('groups.show', ['group' => $group->id, 'tags' => 'laravel,']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SingleGroup/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $link->id));
});

test('a collection accepts repeated tags parameters', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['coffee, tea']);

    $group = groupWithLinks($user, $link);

    $this->actingAs($user)
        ->get(route('groups.show', ['group' => $group->id, 'tags' => ['coffee, tea']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SingleGroup/Index')
            ->has('links.data', 1)
            ->where('links.data.0.id', $link->id));
});
