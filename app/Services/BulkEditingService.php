<?php

namespace App\Services;

use App\Models\Link;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class BulkEditingService
{
    public function handleLinkEditingAction(string $action, array $linkIds, array $groupIds = [], array $tagIds = []): void
    {
        /** @var Collection<int, Link> $links */
        $links = Link::filterByCurrentUser()->whereIn('id', $linkIds)->get();

        match ($action) {
            'add_tags' => $this->addTagsToLinks($links, $tagIds),
            'remove_tags' => $this->removeTagsFromLinks($links, $tagIds),
            'add_to_groups' => $this->addLinksToGroups($links, $groupIds),
            'remove_from_groups' => $this->removeLinksFromGroups($links, $groupIds),
            'delete' => $this->deleteLinks($links),
            default => null,
        };
    }

    /**
     * @param  Collection<int, Link>  $links
     */
    private function addTagsToLinks(Collection $links, array $tagIds): void
    {
        $tags = Tag::whereIn('id', $tagIds)->get();

        foreach ($links as $link) {
            $link->attachTags($tags);
        }
    }

    /**
     * @param  Collection<int, Link>  $links
     */
    private function removeTagsFromLinks(Collection $links, array $tagIds): void
    {
        $tags = Tag::whereIn('id', $tagIds)->get();

        foreach ($links as $link) {
            $link->detachTags($tags);
        }
    }

    /**
     * @param  Collection<int, Link>  $links
     */
    private function addLinksToGroups(Collection $links, array $groupIds): void
    {
        foreach ($links as $link) {
            $link->groups()->syncWithoutDetaching($groupIds);
        }
    }

    /**
     * @param  Collection<int, Link>  $links
     */
    private function removeLinksFromGroups(Collection $links, array $groupIds): void
    {
        foreach ($links as $link) {
            $link->groups()->detach($groupIds);
        }
    }

    /**
     * @param  Collection<int, Link>  $links
     */
    private function deleteLinks(Collection $links): void
    {
        foreach ($links as $link) {
            $link->groups()->detach();
            $link->delete();
        }
    }
}
