<?php

namespace App\Services;

use App\Enums\LinkSource;
use App\Models\Group;
use App\Models\Link;
use App\Models\User;
use App\Services\Models\GroupService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Spatie\Tags\Tag;

class ImportService
{
    public function __construct(
        protected GroupService $groupService,
    ) {
        //
    }

    /**
     * Restore a library from an export, or seed one from a browser's bookmarks.
     *
     * Two shapes arrive here. A file written by `ExportService` carries a
     * `version` and everything the library holds; anything without one is
     * either a browser's bookmarks or an export from before this format, and
     * gets read for the little it does carry. Both go down the same path — the
     * richer fields are simply absent from the thinner files.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $importOptions
     */
    public function importUserData(array $data, array $importOptions, User $user): void
    {
        $shouldImportLinks = in_array('links', $importOptions);
        $shouldImportGroups = in_array('groups', $importOptions);

        $groupsByExportedId = [];

        if ($shouldImportGroups && isset($data['groups'])) {
            $groupsByExportedId = $this->importGroups($data['groups'], $user);
        }

        if ($shouldImportLinks && isset($data['links'])) {
            $this->importLinks($data['links'], $groupsByExportedId, $user);
        }
    }

    /**
     * Recreate the collections, parents before children.
     *
     * A collection is identified by where it sits, not just by what it is
     * called: "Backend" inside "Reading" is a different collection from
     * "Backend" inside "Work", and matching on the title alone would fold the
     * two together and drag every link into whichever won. So the parent is
     * resolved first, and the match is made on the pair.
     *
     * The returned map is keyed by the id the file used, so links can find the
     * collections they belonged to.
     *
     * @param  array<int, array<string, mixed>>  $groupsData
     * @return array<int|string, Group>
     */
    private function importGroups(array $groupsData, User $user): array
    {
        $dataByExportedId = [];

        foreach ($groupsData as $groupData) {
            $title = $groupData['title'] ?? null;

            if (! $title) {
                continue;
            }

            $exportedId = $groupData['id'] ?? null;

            /** Older files list collections by title alone, with nothing to point at. */
            if ($exportedId === null) {
                Group::firstOrCreate([
                    'title' => $title,
                    'user_id' => $user->id,
                ]);

                continue;
            }

            $dataByExportedId[$exportedId] = $groupData;
        }

        $groupsByExportedId = [];
        $inProgress = [];

        foreach (array_keys($dataByExportedId) as $exportedId) {
            $this->resolveGroup($exportedId, $dataByExportedId, $groupsByExportedId, $inProgress, $user);
        }

        return $groupsByExportedId;
    }

    /**
     * Create one collection once its parent exists, recursing upwards.
     *
     * A collection already part-way through being resolved is its own
     * ancestor — a loop the file should never contain, but might. Such a
     * collection is treated as having no parent at all, which breaks the loop
     * and leaves it at the top level rather than hanging the import.
     *
     * @param  array<int|string, array<string, mixed>>  $dataByExportedId
     * @param  array<int|string, Group>  $groupsByExportedId
     * @param  array<int|string, bool>  $inProgress
     */
    private function resolveGroup(
        int|string $exportedId,
        array $dataByExportedId,
        array &$groupsByExportedId,
        array &$inProgress,
        User $user,
    ): ?Group {
        if (isset($groupsByExportedId[$exportedId])) {
            return $groupsByExportedId[$exportedId];
        }

        if (! isset($dataByExportedId[$exportedId]) || isset($inProgress[$exportedId])) {
            return null;
        }

        $inProgress[$exportedId] = true;

        $groupData = $dataByExportedId[$exportedId];
        $parentExportedId = $groupData['parent_id'] ?? null;

        $parent = $parentExportedId === null
            ? null
            : $this->resolveGroup($parentExportedId, $dataByExportedId, $groupsByExportedId, $inProgress, $user);

        unset($inProgress[$exportedId]);

        $group = Group::firstOrCreate([
            'title' => $groupData['title'],
            'user_id' => $user->id,
            'parent_group_id' => $parent?->id,
        ]);

        $queryOptions = $this->queryOptionsFromTagNames($groupData['query_options'] ?? []);

        if ($queryOptions !== []) {
            $group->query_options = $queryOptions;
            $group->save();
        }

        $groupsByExportedId[$exportedId] = $group;

        return $group;
    }

    /**
     * Turn a collection's rules back into tag ids.
     *
     * The file names tags rather than numbering them, because the numbers mean
     * nothing in the database it is being read into.
     *
     * @param  mixed  $queryOptions
     * @return array<string, array<int, int>>
     */
    private function queryOptionsFromTagNames($queryOptions): array
    {
        if (! is_array($queryOptions)) {
            return [];
        }

        $resolved = [];

        foreach (Group::TAG_RULE_KEYS as $key) {
            $names = $queryOptions[$key] ?? [];

            if (! is_array($names)) {
                continue;
            }

            $tagIds = [];

            foreach ($names as $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $tagIds[] = Tag::findOrCreateFromString($name)->id;
            }

            if ($tagIds !== []) {
                $resolved[$key] = $tagIds;
            }
        }

        return $this->groupService->cleanupQueryOptions($resolved);
    }

    /**
     * @param  array<int, array<string, mixed>>  $linksData
     * @param  array<int|string, Group>  $groupsByExportedId
     */
    private function importLinks(array $linksData, array $groupsByExportedId, User $user): void
    {
        foreach ($linksData as $linkData) {
            $url = $linkData['link'] ?? null;

            if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > Link::MAX_URL_LENGTH) {
                continue;
            }

            $link = Link::withTrashed()
                ->where('link', $url)
                ->where('user_id', $user->id)
                ->oldest('id')
                ->first();

            if (! $link) {
                $link = $this->createLink($linkData, $url, $user);
            }

            /**
             * Tags are added, never replaced. A link the user already has may
             * have been tagged since, and an import that quietly dropped those
             * tags because the file did not mention them would lose work the
             * file knows nothing about. Restoring into an empty account is
             * unaffected — there is nothing there to keep.
             */
            if (! empty($linkData['tags']) && is_array($linkData['tags'])) {
                $tags = array_map(
                    fn (string $name) => Tag::findOrCreateFromString($name),
                    $linkData['tags']
                );

                $link->attachTags($tags);
            }

            $groupIds = $this->groupIdsFor($linkData, $groupsByExportedId);

            if ($groupIds !== []) {
                $link->groups()->syncWithoutDetaching($groupIds);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $linkData
     */
    private function createLink(array $linkData, string $url, User $user): Link
    {
        $link = new Link;

        $link->user_id = $user->id;
        $link->link = $url;
        $link->title = $linkData['title'] ?? null;
        $link->description = $linkData['description'] ?? null;
        $link->is_favorite = (bool) ($linkData['is_favorite'] ?? false);
        $link->rating = $this->rating($linkData['rating'] ?? null);
        $link->read_at = $this->date($linkData['read_at'] ?? null);
        $link->favicon_url = $linkData['favicon_url'] ?? null;
        $link->preview_image_url = $linkData['preview_image_url'] ?? null;
        $link->metadata_fetched_at = $this->date($linkData['metadata_fetched_at'] ?? null);
        $link->source = $this->source($linkData['source'] ?? null);

        /**
         * Eloquent leaves the timestamps alone when they are already dirty, so
         * setting them here is what keeps a restored library in the order the
         * user built it rather than stamping it all with the import.
         */
        $createdAt = $this->date($linkData['created_at'] ?? null);
        $updatedAt = $this->date($linkData['updated_at'] ?? null);

        if ($createdAt !== null) {
            $link->created_at = $createdAt;
        }

        if ($updatedAt !== null) {
            $link->updated_at = $updatedAt;
        }

        $link->save();

        if (! empty($linkData['archived'])) {
            $link->delete();
        }

        return $link;
    }

    /**
     * @param  array<string, mixed>  $linkData
     * @param  array<int|string, Group>  $groupsByExportedId
     * @return array<int, int>
     */
    private function groupIdsFor(array $linkData, array $groupsByExportedId): array
    {
        $exportedIds = $linkData['group_ids'] ?? [];

        if (! is_array($exportedIds) || $groupsByExportedId === []) {
            return [];
        }

        $groupIds = [];

        foreach ($exportedIds as $exportedId) {
            if (isset($groupsByExportedId[$exportedId])) {
                $groupIds[] = $groupsByExportedId[$exportedId]->id;
            }
        }

        return array_values(array_unique($groupIds));
    }

    private function rating(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $rating = (int) $value;

        return $rating >= 1 && $rating <= 5 ? $rating : null;
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * An import claims a source only if it is one this application knows;
     * anything else is recorded for what it actually was.
     */
    private function source(mixed $value): LinkSource
    {
        if (! is_string($value)) {
            return LinkSource::Import;
        }

        return LinkSource::tryFrom($value) ?? LinkSource::Import;
    }
}
