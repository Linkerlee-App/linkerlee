<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Link;
use App\Models\Tag;
use App\Services\Models\GroupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
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
        return Inertia::render('Tags/Index', [
            'tags' => Tag::orderByName()
                ->filterByCurrentUser()
                ->withCount(['links' => fn (Builder $query) => $query->filterByCurrentUser()])
                ->get()
                ->transform(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'links_count' => $tag->links_count,
                ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        // Tags carry no owner column — a tag is "yours" because one of your
        // links wears it — so this only has to avoid minting a duplicate row
        // for a name that already exists.
        Tag::findOrCreate($request->validated('tagName'));

        return Redirect::back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorizeTagUsage($tag);

        $name = $request->validated('tagName');

        if ($this->isUsedByAnotherUser($tag)) {
            // Renaming the row itself would rename the tag under everyone else
            // using it, so the current user's links move to a tag carrying the
            // new name and the original is left alone for its other users.
            $replacement = Tag::findOrCreate($name);

            $this->taggedLinksOfCurrentUser($tag)->each(function (Link $link) use ($tag, $replacement): void {
                $link->detachTag($tag);
                $link->attachTag($replacement);
            });

            $this->groupService->replaceTagInQueryOptions($tag->id, $replacement->id, Auth::user());
        } else {
            $tag->name = $name;
            $tag->save();
        }

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorizeTagUsage($tag);

        // `taggables.tag_id` cascades, so deleting the row outright stripped
        // the tag from every user filed under it. Deleting a tag means dropping
        // it from your own links; the shared row only goes once nothing points
        // at it any more.
        $this->taggedLinksOfCurrentUser($tag)->each(fn (Link $link) => $link->detachTag($tag));

        if (! $this->isStillInUse($tag)) {
            $tag->delete();
        }

        $this->groupService->removeDeletedTagFromQueryOptions($tag->id, Auth::user());
        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return Redirect::back();
    }

    /**
     * A tag is reachable only by a user who has a link filed under it. Someone
     * else's tag 404s rather than 403s, so an id cannot be probed for existence.
     */
    protected function authorizeTagUsage(Tag $tag): void
    {
        abort_unless(Tag::filterByCurrentUser()->whereKey($tag->id)->exists(), 404);
    }

    /**
     * The current user's links wearing this tag, archived ones included — a
     * tag the user has removed should not reappear when a link is restored.
     *
     * @return Collection<int, Link>
     */
    protected function taggedLinksOfCurrentUser(Tag $tag): Collection
    {
        return Link::withTrashed()
            ->filterByCurrentUser()
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id))
            ->get();
    }

    protected function isUsedByAnotherUser(Tag $tag): bool
    {
        return DB::table('taggables')
            ->where('tag_id', $tag->id)
            ->where('taggable_type', Link::class)
            ->whereNotIn('taggable_id', Link::withTrashed()->filterByCurrentUser()->select('id'))
            ->exists();
    }

    protected function isStillInUse(Tag $tag): bool
    {
        return DB::table('taggables')->where('tag_id', $tag->id)->exists();
    }

    /**
     * Returns all tags for the current user.
     */
    public static function getAllTags(): array
    {
        return Tag::orderByName()
            ->filterByCurrentUser()
            ->get()
            ->transform(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])
            ->toArray();
    }

    /**
     * Returns tags by their names.
     *
     * @param  array<int, string>  $names
     * @return array<int, array{id: int, name: string}>
     */
    public static function getTagsByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $locale = Tag::getLocale();

        return Tag::filterByCurrentUser()
            ->where(function (Builder $query) use ($names, $locale) {
                foreach ($names as $name) {
                    $query->orWhere("name->$locale", $name);
                }
            })
            ->get()
            ->transform(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])
            ->toArray();
    }

    /**
     * Returns all tags of the given link.
     */
    public static function getTagsOfLink($link): array
    {
        $tags = $link->tags;
        $linkTags = [];

        foreach ($tags as $tag) {
            $linkTags[] = (object) [
                'id' => $tag->id,
                'name' => $tag->name,
            ];
        }

        return $linkTags;
    }
}
