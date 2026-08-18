<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Link;
use App\Models\User;
use Spatie\Tags\Tag;

class ImportService
{
    public function importUserData(array $data, array $importOptions, User $user): void
    {
        $shouldImportLinks = in_array('links', $importOptions);
        $shouldImportGroups = in_array('groups', $importOptions);

        if ($shouldImportGroups && isset($data['groups'])) {
            foreach ($data['groups'] as $groupData) {
                $title = $groupData['title'] ?? null;

                if (! $title) {
                    continue;
                }

                Group::firstOrCreate([
                    'title' => $title,
                    'user_id' => $user->id,
                ]);
            }
        }

        if ($shouldImportLinks && isset($data['links'])) {
            foreach ($data['links'] as $linkData) {
                $url = $linkData['link'] ?? null;

                if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > Link::MAX_URL_LENGTH) {
                    continue;
                }

                $link = Link::where('link', $url)->where('user_id', $user->id)->first();

                if (! $link) {
                    $link = new Link;
                    $link->user_id = $user->id;
                    $link->link = $url;
                    $link->title = $linkData['title'] ?? null;
                    $link->save();
                }

                if (! empty($linkData['tags']) && is_array($linkData['tags'])) {
                    $tags = array_map(
                        fn (string $name) => Tag::findOrCreateFromString($name),
                        $linkData['tags']
                    );

                    $link->syncTags($tags);
                }
            }
        }
    }
}
