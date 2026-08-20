<?php

use App\Models\Link;
use App\Models\User;

test('the tags index lists each tag with its number of links', function () {
    $user = User::factory()->create();

    $first = Link::factory()->create(['user_id' => $user->id]);
    $first->attachTags(['laravel', 'php']);
    $second = Link::factory()->create(['user_id' => $user->id]);
    $second->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tags/Index')
            ->has('tags', 2)
            ->where('tags.0.name', 'laravel')
            ->where('tags.0.links_count', 2)
            ->where('tags.1.name', 'php')
            ->where('tags.1.links_count', 1));
});

test('the link count of a tag ignores links of other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownLink = Link::factory()->create(['user_id' => $user->id]);
    $ownLink->attachTags(['laravel']);
    $otherLink = Link::factory()->create(['user_id' => $other->id]);
    $otherLink->attachTags(['laravel']);

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tags/Index')
            ->has('tags', 1)
            ->where('tags.0.links_count', 1));
});

test('tags are listed alphabetically regardless of name length or case', function () {
    $user = User::factory()->create();

    $link = Link::factory()->create(['user_id' => $user->id]);
    $link->attachTags(['php', 'Laravel', 'zsh', 'ansible']);

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tags/Index')
            ->where('tags.0.name', 'ansible')
            ->where('tags.1.name', 'Laravel')
            ->where('tags.2.name', 'php')
            ->where('tags.3.name', 'zsh'));
});

test('the link count of a tag ignores archived links', function () {
    $user = User::factory()->create();

    $kept = Link::factory()->create(['user_id' => $user->id]);
    $kept->attachTags(['laravel']);
    $archived = Link::factory()->create(['user_id' => $user->id]);
    $archived->attachTags(['laravel']);
    $archived->delete();

    $this->actingAs($user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tags/Index')
            ->where('tags.0.links_count', 1));
});
