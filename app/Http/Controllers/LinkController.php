<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Http\Resources\LinkResource;
use App\Jobs\FetchLinkMetadataJob;
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
    public function index(): Response
    {
        $searchString = Request::get('search') ?? '';
        $filteredTags = $this->requestedTagNames();
        $showUntaggedOnly = Request::boolean('untaggedOnly');
        $showUnreadOnly = Request::boolean('unreadOnly');
        $showFavoritesOnly = Request::boolean('favorite');

        return Inertia::render('Links/Index', [
            'links' => Inertia::scroll(fn () => Link::with(['tags', 'groups'])
                ->orderBy('created_at', 'desc')
                ->filterByCurrentUser()
                ->filterLinks($searchString, $filteredTags, $showUntaggedOnly, $showUnreadOnly, $showFavoritesOnly)
                ->cursorPaginate(20)
                ->through(fn (Link $link) => LinkResource::make($link)->resolve())),
            'searchString' => $searchString,
            'filteredTags' => $this->resolveFilteredTags($filteredTags),
            'showUntaggedOnly' => $showUntaggedOnly,
            'showUnreadOnly' => $showUnreadOnly,
            'showFavoritesOnly' => $showFavoritesOnly,
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

        $link->save();

        FetchLinkMetadataJob::dispatch($link);

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
        $link = Link::with(['tags', 'groups'])->filterByCurrentUser()->find($link);

        if ($link === null) {
            return Redirect::route('links.index');
        }

        return Inertia::render('SingleLink/Index', [
            'link' => LinkResource::make($link)->resolve(),
            'allTags' => TagController::getAllTags(),
            'allGroups' => Group::orderBy('title')
                ->filterByCurrentUser()
                ->get()
                ->map(fn (Group $group) => ['id' => $group->id, 'title' => $group->title]),
        ]);
    }

    /**
     * Editing happens inline on the link detail views.
     */
    public function edit(Link $link): RedirectResponse
    {
        $this->authorizeOwnership($link);

        return Redirect::route('links.show', $link->id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLinkRequest $request, Link $link)
    {
        $this->authorizeOwnership($link);

        $validated = $request->validated();

        $link->link = $validated['link'];
        $link->title = $validated['title'];
        $link->description = $validated['description'] ?? null;

        $urlChanged = $link->isDirty('link');

        $link->save();

        if ($urlChanged || empty($link->title) || $link->metadata_fetched_at === null) {
            FetchLinkMetadataJob::dispatch($link);
        }

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
     * Permanently remove the specified resource from storage.
     */
    public function destroy(Link $link): RedirectResponse
    {
        $this->authorizeOwnership($link);

        $this->forceDeleteLink($link);

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::route('links.index');
    }

    /**
     * Soft-delete (archive) a link.
     */
    public function archive(Link $link): RedirectResponse
    {
        $this->authorizeOwnership($link);

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
     * Permanently delete a trashed link.
     */
    public function forceDestroy(int $link): RedirectResponse
    {
        $link = Link::withTrashed()->filterByCurrentUser()->findOrFail($link);

        $this->forceDeleteLink($link);

        return Redirect::route('links.trashed');
    }

    /**
     * Permanently delete all trashed links of the current user.
     */
    public function emptyTrash(): RedirectResponse
    {
        Link::onlyTrashed()
            ->filterByCurrentUser()
            ->get()
            ->each(fn (Link $link) => $this->forceDeleteLink($link));

        return Redirect::route('links.trashed');
    }

    /**
     * Toggle the favorite status of a link.
     */
    public function toggleFavorite(Link $link): JsonResponse
    {
        $this->authorizeOwnership($link);

        $link->is_favorite = ! $link->is_favorite;
        $link->save();

        return response()->json(['is_favorite' => $link->is_favorite]);
    }

    /**
     * Toggle the read (read-later) status of a link.
     */
    public function toggleRead(Link $link): JsonResponse
    {
        $this->authorizeOwnership($link);

        $link->read_at = $link->read_at === null ? now() : null;
        $link->save();

        return response()->json(['read_at' => $link->read_at?->toISOString()]);
    }

    /**
     * Set the rating for a link.
     */
    public function rate(Link $link): JsonResponse
    {
        $this->authorizeOwnership($link);

        Request::validate(['rating' => 'nullable|integer|min:1|max:5']);

        $link->rating = Request::get('rating');
        $link->save();

        return response()->json(['rating' => $link->rating]);
    }

    /**
     * The tag names the listing should be filtered by.
     *
     * Tags are sent as repeated `tags[]` parameters so that a name containing
     * a comma survives the round trip; the older comma-separated form is still
     * accepted for links shared or bookmarked before that change.
     *
     * @return array<int, string>
     */
    protected function requestedTagNames(): array
    {
        $requested = Request::get('tags');

        if (! is_array($requested)) {
            $requested = $requested === null || $requested === ''
                ? []
                : explode(',', (string) $requested);
        }

        return collect($requested)
            ->filter(fn ($name) => is_string($name))
            ->map(fn (string $name) => trim($name))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the requested tag names into filter options for the UI.
     *
     * A name that no longer matches a tag is kept without an id: the listing
     * is still filtered by it, so dropping it here would leave the user with
     * an empty page and nothing explaining why.
     *
     * @param  array<int, string>  $names
     * @return array<int, array{id: int|null, name: string}>
     */
    protected function resolveFilteredTags(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $resolved = collect(TagController::getTagsByNames($names))->keyBy('name');

        return collect($names)
            ->map(fn (string $name): array => [
                'id' => $resolved->get($name)['id'] ?? null,
                'name' => $name,
            ])
            ->all();
    }

    protected function authorizeOwnership(Link $link): void
    {
        abort_unless($link->user_id === Auth::id(), 404);
    }

    protected function forceDeleteLink(Link $link): void
    {
        $link->groups()->detach();
        $link->syncTags([]);
        $link->forceDelete();
    }
}
