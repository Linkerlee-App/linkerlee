<?php

namespace App\Http\Controllers;

use App\Helpers\WebpageData;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use App\Services\Models\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    public function __construct(
        protected GroupService $groupService,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(array $filteredTags = []): Response
    {
        $searchString = Request::get('search') ?? '';
        $filteredTags = Request::get('tags') ?? '';
        $showUntaggedOnly = Request::get('untaggedOnly') ?? false;

        $filteredTags = empty($filteredTags) ? [] : explode(',', $filteredTags);

        return Inertia::render('Links/Index', [
            'links' => Inertia::scroll(fn () => Link::with(['tags', 'groups'])
                ->orderBy('created_at', 'desc')
                ->filterByCurrentUser()
                ->filterLinks($searchString, $filteredTags, $showUntaggedOnly)
                ->cursorPaginate(20)
                ->through(fn (Link $link) => [
                    'id' => $link->id,
                    'title' => $link->title,
                    'description' => $link->description,
                    'link' => $link->link,
                    'is_favorite' => $link->is_favorite,
                    'rating' => $link->rating,
                    'tags' => $link->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
                    'tag_ids' => $link->tags->pluck('id')->toArray(),
                    'linkGroups' => $link->groups->sortBy('title')->values()->map(fn ($g) => ['id' => $g->id, 'title' => $g->title])->values(),
                    'group_ids' => $link->groups->pluck('id')->toArray(),
                    'created_at' => $link->getCreatedAtForHumansAttribute(),
                    'created_at_with_time' => $link->getCreatedAtForHumansAttribute(true),
                ])),
            'searchString' => $searchString,
            'filteredTags' => $filteredTags ? TagController::getTagsByNames($filteredTags) : [],
            'showUntaggedOnly' => $showUntaggedOnly,
            'allTags' => TagController::getAllTags(),
            'allGroups' => Group::orderBy('title')
                ->filterByCurrentUser()
                ->get()
                ->map(fn (Group $group) => ['id' => $group->id, 'title' => $group->title]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Links/Create', [
            'tags' => TagController::getAllTags(),
            'groups' => Group::orderBy('title')
                ->filterByCurrentUser()
                ->get()
                ->map(fn (Group $group) => ['id' => $group->id, 'title' => $group->title]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $link = Link::make();

        $link->link = $validated['link'];
        $link->title = $validated['title'];
        $link->description = $validated['description'] ?? null;
        $link->user_id = Auth::id();

        if (empty($link->title)) {
            $link->title = WebpageData::getWebPageTitle($link->link);
        }

        $link->save();

        $groupIds = $validated['groups'];

        $link->groups()->sync($groupIds);

        $tags = [];

        foreach ($validated['tags'] as $tag) {
            $tags[] = Tag::filterByCurrentUser()->find($tag);
        }

        foreach ($validated['newTags'] ?? [] as $name) {
            $tags[] = Tag::findOrCreate($name);
        }

        $link->syncTags($tags);

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::route('links.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $link): Response|RedirectResponse
    {
        $link = Link::filterByCurrentUser()->find($link);

        if ($link === null) {
            return Redirect::route('links.index');
        }

        return Inertia::render('SingleLink/Index', [
            'link' => (object) [
                'title' => $link->title,
                'description' => $link->description,
                'link' => $link->link,
                'id' => $link->id,
                'is_favorite' => $link->is_favorite,
                'rating' => $link->rating,
                'tags' => TagController::getTagsOfLink($link),
                'linkGroups' => $link->groups
                    ->sortBy('title')
                    ->values()
                    ->transform(fn (Group $group) => [
                        'id' => $group->id,
                        'title' => $group->title,
                    ]),
                'groups' => $link->groupIds(),
                'created_at' => $link->getCreatedAtForHumansAttribute(),
                'updated_at' => $link->getUpdatedAtForHumansAttribute(),
                'created_at_with_time' => $link->getCreatedAtForHumansAttribute(true),
                'updated_at_with_time' => $link->getUpdatedAtForHumansAttribute(true),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Link $link): Response|RedirectResponse
    {
        if (! $link->user_id === Auth::id()) {
            return Redirect::route('links.index');
        }

        return Inertia::render('Links/Edit', [
            'link' => [
                'id' => $link->id,
                'title' => $link->title,
                'description' => $link->description,
                'link' => $link->link,
                'tags' => $link->tagIds(),
                'groups' => $link->groupIds(),
            ],
            'tags' => TagController::getAllTags(),
            'groups' => Group::orderBy('title')
                ->filterByCurrentUser()
                ->get()
                ->map(fn (Group $group) => ['id' => $group->id, 'title' => $group->title]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLinkRequest $request, Link $link)
    {
        $validated = $request->validated();

        $link->link = $validated['link'];
        $link->title = $validated['title'];
        $link->description = $validated['description'] ?? null;

        if (empty($link->title)) {
            $link->title = WebpageData::getWebPageTitle($link->link);
        }

        $link->save();

        $groupIds = $validated['groups'];

        $link->groups()->sync($groupIds);

        $tags = [];

        foreach ($validated['tags'] as $tag) {
            $tags[] = Tag::filterByCurrentUser()->find($tag);
        }

        foreach ($validated['newTags'] ?? [] as $name) {
            $tags[] = Tag::findOrCreate($name);
        }

        $link->syncTags($tags);

        $this->groupService->updateUserGroupsLinkCount(Auth::user());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Link $link): RedirectResponse
    {
        $link->groups()->detach();

        $link->delete();

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::route('links.index');
    }

    /**
     * Soft-delete (archive) a link.
     */
    public function archive(Link $link): RedirectResponse
    {
        $link->delete();

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::route('links.index');
    }

    /**
     * Restore a soft-deleted link.
     */
    public function restore(int $link): RedirectResponse
    {
        $link = Link::withTrashed()->filterByCurrentUser()->findOrFail($link);

        $link->restore();

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::route('links.trashed');
    }

    /**
     * Display soft-deleted links.
     */
    public function trashed(): Response
    {
        return Inertia::render('Links/Trashed', [
            'links' => Link::onlyTrashed()
                ->filterByCurrentUser()
                ->orderBy('deleted_at', 'desc')
                ->get()
                ->map(fn (Link $link) => [
                    'id' => $link->id,
                    'title' => $link->title,
                    'link' => $link->link,
                    'deleted_at' => $link->deleted_at->format('d.m.Y'),
                ]),
        ]);
    }

    /**
     * Toggle the favorite status of a link.
     */
    public function toggleFavorite(Link $link): JsonResponse
    {
        $link->is_favorite = ! $link->is_favorite;
        $link->save();

        return response()->json(['is_favorite' => $link->is_favorite]);
    }

    /**
     * Set the rating for a link.
     */
    public function rate(Link $link): JsonResponse
    {
        Request::validate(['rating' => 'nullable|integer|min:1|max:5']);

        $link->rating = Request::get('rating');
        $link->save();

        return response()->json(['rating' => $link->rating]);
    }
}
