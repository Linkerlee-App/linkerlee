<?php

namespace App\Services\Models;

use App\Models\Group;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;

class GroupService
{
    /**
     * Normalise a set of tag rules for storage: integers, no duplicates, no
     * gaps in the keys — a filtered array with gaps serialises to a JSON object
     * rather than a list — and no empty rule keys at all.
     *
     * @param  array<string, mixed>  $queryOptions
     * @return array<string, array<int, int>>
     */
    public function cleanupQueryOptions(array $queryOptions): array
    {
        foreach ($queryOptions as $key => $value) {
            $tagIds = array_values(array_unique(array_map(
                intval(...),
                array_filter((array) $value, fn ($id) => $id !== null && $id !== ''),
            )));

            if ($tagIds === []) {
                unset($queryOptions[$key]);

                continue;
            }

            $queryOptions[$key] = $tagIds;
        }

        return $queryOptions;
    }

    /**
     * Resolve a group's tag rules to `{id, name}` pairs from a tag map that was
     * fetched once for the whole listing, so a page of collections does not run
     * three tag queries each.
     *
     * @param  Collection<int, Tag>  $tagsById
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    public function presentRules(Group $group, Collection $tagsById): array
    {
        $queryOptions = $group->query_options ?? [];

        $rules = [];

        foreach (Group::TAG_RULE_KEYS as $field => $key) {
            $rules[$field] = collect($queryOptions[$key] ?? [])
                ->map(fn ($id) => $tagsById->get($id))
                ->filter()
                ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all();
        }

        return $rules;
    }

    /**
     * The tag models named by any rule across the given collections, keyed by id.
     *
     * @param  Collection<int, Group>  $groups
     * @return Collection<int, Tag>
     */
    public function ruleTagsFor(Collection $groups): Collection
    {
        $ids = $groups->flatMap->ruleTagIds()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Tag::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Recalculate and persist links_count for all groups belonging to the user.
     */
    public function updateUserGroupsLinkCount(User $user): void
    {
        Group::where('user_id', $user->id)->get()->each(function (Group $group): void {
            $group->updateLinksCount();
            $group->save();
        });
    }

    /**
     * Remove a deleted tag ID from all group query options for the given user.
     */
    public function removeDeletedTagFromQueryOptions(int $tagId, User $user): void
    {
        Group::where('user_id', $user->id)->get()->each(function (Group $group) use ($tagId): void {
            $queryOptions = $group->query_options ?? [];
            $changed = false;

            foreach ($queryOptions as $key => $tagIds) {
                $filtered = array_values(array_filter($tagIds, fn ($id) => $id !== $tagId));

                if (count($filtered) !== count($tagIds)) {
                    $queryOptions[$key] = $filtered;
                    $changed = true;
                }
            }

            if ($changed) {
                $group->query_options = $queryOptions;
                $group->save();
            }
        });
    }
}
