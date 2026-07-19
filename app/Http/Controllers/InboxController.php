<?php

namespace App\Http\Controllers;

use App\Http\Resources\LinkResource;
use App\Models\Group;
use App\Models\Link;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $searchString = Request::string('search');
        $showUntagged = Request::boolean('untagged', true);
        $showUngrouped = Request::boolean('ungrouped', true);

        return Inertia::render('Inbox/Index', [
            'links' => Inertia::scroll(fn () => Link::with(['tags', 'groups'])
                ->orderBy('created_at', 'desc')
                ->filterByCurrentUser()
                ->when($showUntagged, fn ($query) => $query->whereDoesntHave('tags'))
                ->when($showUngrouped, fn ($query) => $query->whereDoesntHave('groups'))
                ->filterLinks($searchString)
                ->cursorPaginate(20)
                ->through(fn (Link $link) => LinkResource::make($link)->resolve())),
            'searchString' => $searchString,
            'untagged' => $showUntagged,
            'ungrouped' => $showUngrouped,
            'allTags' => TagController::getAllTags(),
            'allGroups' => Group::orderBy('title')
                ->filterByCurrentUser()
                ->get()
                ->map(fn (Group $group) => ['id' => $group->id, 'title' => $group->title]),
        ]);
    }
}
