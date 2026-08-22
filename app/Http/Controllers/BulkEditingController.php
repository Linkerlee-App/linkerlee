<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\BulkEditingService;
use App\Services\Models\GroupService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class BulkEditingController extends Controller
{
    public function __construct(
        protected GroupService $groupService,
    ) {
        //
    }

    public function editLinks(Request $request, BulkEditingService $bulkEditingService): void
    {
        $action = $request::get('action');

        // An action only sends the lists it needs — tagging sends no groups —
        // and the missing ones arrived as null, which crashed the service.
        $links = (array) ($request::get('links') ?? []);
        $tags = (array) ($request::get('tags') ?? []);
        $groups = $this->ownedGroupIds((array) ($request::get('groups') ?? []));

        $bulkEditingService->handleLinkEditingAction($action, $links, $groups, $tags);

        // Every bulk action moves links in or out of a collection, either
        // directly or by changing the tags its rules match on, so the stored
        // counts the collections listing reads are all suspect afterwards.
        $this->groupService->updateUserGroupsLinkCount(Auth::user());
    }

    /**
     * Keep only the collections the user actually owns, so a hand-made request
     * cannot file links into a stranger's collection.
     *
     * @param  array<int, mixed>  $groupIds
     * @return array<int, int>
     */
    protected function ownedGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Group::filterByCurrentUser()
            ->whereIn('id', $groupIds)
            ->pluck('id')
            ->all();
    }
}
