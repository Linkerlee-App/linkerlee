<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExportService
{
    public function exportUserData(User $user): array
    {
        $links = Link::where('user_id', $user->id)
            ->with('tags')
            ->get()
            ->map(fn (Link $link) => [
                'title' => $link->title,
                'link' => $link->link,
                'tags' => $link->tags->pluck('name')->toArray(),
            ])->toArray();

        $userLinkIds = Link::where('user_id', $user->id)->select('id');

        $tags = Tag::whereIn('id',
            DB::table('taggables')
                ->select('tag_id')
                ->where('taggable_type', Link::class)
                ->whereIn('taggable_id', $userLinkIds)
        )->get()
            ->map(fn (Tag $tag) => [
                'name' => $tag->name,
            ])->toArray();

        $groups = Group::where('user_id', $user->id)
            ->get()
            ->map(fn (Group $group) => [
                'title' => $group->title,
            ])->toArray();

        return [
            'links' => $links,
            'tags' => $tags,
            'groups' => $groups,
        ];
    }
}
