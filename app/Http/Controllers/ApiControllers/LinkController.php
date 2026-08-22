<?php

namespace App\Http\Controllers\ApiControllers;

use App\Enums\LinkSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkApiRequest;
use App\Http\Requests\UpdateLinkApiRequest;
use App\Models\Link;
use App\Models\Tag;
use App\Services\Models\GroupService;
use App\Services\Models\LinkCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LinkController extends Controller
{
    public function __construct(
        protected GroupService $groupService,
        protected LinkCreationService $linkCreationService,
    ) {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLinkApiRequest $request): Response
    {
        $validated = $request->validated();

        $link = $this->linkCreationService->create(
            Auth::user(),
            $validated['link'],
            LinkSource::fromApiClient($request->headers->get('Origin'), $validated['source'] ?? null),
            $validated['title'] ?? null,
        );

        if (array_key_exists('groups', $validated)) {
            $groupIds = $validated['groups'];

            $link->groups()->sync($groupIds);
        }

        $tags = [];

        foreach ($validated['tags'] ?? [] as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        foreach ($validated['newTags'] ?? [] as $name) {
            $tags[] = Tag::findOrCreate($name);
        }

        if ($tags !== []) {
            $link->syncTags($tags);
        }

        // The tags decide which collections gather this link, so the counts
        // have to be recomputed even when no collection was named outright.
        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return response('The link was added.', headers: ['Content-Type' => 'text/plain']);
    }

    /**
     * Look up an existing link by URL for the authenticated user.
     */
    public function find(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('create')) {
            abort(403);
        }

        $validated = $request->validate([
            'link' => ['required', 'url'],
        ]);

        $link = Link::with('tags')
            ->where('user_id', Auth::id())
            ->where('link', $validated['link'])
            ->first();

        if ($link === null) {
            return response()->json(null, 404);
        }

        return response()->json($this->presentLink($link));
    }

    /**
     * Update an existing link (title + tags). The URL itself is immutable here.
     */
    public function update(UpdateLinkApiRequest $request, int $linkId): JsonResponse
    {
        $link = Link::where('user_id', Auth::id())->find($linkId);

        if ($link === null) {
            abort(404);
        }

        $validated = $request->validated();

        if (array_key_exists('title', $validated) && $validated['title'] !== null) {
            $link->title = $validated['title'];
            $link->save();
        }

        $tags = [];

        foreach ($validated['tags'] ?? [] as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }

        foreach ($validated['newTags'] ?? [] as $name) {
            $tags[] = Tag::findOrCreate($name);
        }

        $link->syncTags($tags);

        // Retagging alone can move the link in or out of a rule-driven
        // collection, so the stored counts have to follow.
        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return response()->json($this->presentLink($link->fresh('tags')));
    }

    /**
     * Remove a link belonging to the authenticated user.
     *
     * Mirrors the web controller's destroy() so both paths leave the database in
     * the same state. Link soft-deletes, so the row is recoverable from the
     * trash with its tags — Link::bootHasTags() overrides the package hook to
     * detach only on a force delete. Group membership is not preserved: the
     * detach below is unconditional, so a restored link comes back ungrouped.
     */
    public function destroy(Request $request, int $linkId): Response
    {
        // The extension's tokens are minted with only the 'create' ability
        // (Settings\ApiTokenController), so that is what authorizes this too —
        // a dedicated 'delete' ability would reject every token already issued.
        if (! $request->user()->tokenCan('create')) {
            abort(403);
        }

        // Scoped by owner, and 404 rather than 403 for someone else's link, so a
        // caller cannot probe which ids exist.
        $link = Link::where('user_id', Auth::id())->find($linkId);

        if ($link === null) {
            abort(404);
        }

        $link->groups()->detach();

        $link->delete();

        $this->groupService->updateUserGroupsLinkCount(Auth::user());

        return response()->noContent();
    }

    private function presentLink(Link $link): array
    {
        return [
            'id' => $link->id,
            'title' => $link->title,
            'link' => $link->link,
            'read_at' => $link->read_at?->toISOString(),
            'source' => $link->source?->value,
            'favicon_url' => $link->favicon_url,
            'preview_image_url' => $link->preview_image_url,
            'tags' => $link->tags
                ->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
        ];
    }
}
