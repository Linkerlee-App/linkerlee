<?php

namespace App\Services\Models;

use App\Models\Group;
use App\Models\User;

class GroupService
{
    /**
     * Remove null/empty values from group query options.
     *
     * @param  array<string, mixed>  $queryOptions
     * @return array<string, mixed>
     */
    public function cleanupQueryOptions(array $queryOptions): array
    {
        foreach ($queryOptions as $key => $value) {
            if (empty($value)) {
                unset($queryOptions[$key]);
            } else {
                $queryOptions[$key] = array_filter($value, fn ($v) => $v !== null);
            }
        }

        return $queryOptions;
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
