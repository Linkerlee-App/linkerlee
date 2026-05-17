<?php

namespace App\Http\Controllers\ApiControllers;

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
            $validated['title'] ?? null,
        );

        if (array_key_exists('groups', $validated)) {
            $groupIds = $validated['groups'];

            $link->groups()->sync($groupIds);

            $this->groupService->updateUserGroupsLinkCount(Auth::user());
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

        return response()->json($this->presentLink($link->fresh('tags')));
    }

    private function presentLink(Link $link): array
    {
        return [
            'id' => $link->id,
            'title' => $link->title,
            'link' => $link->link,
            'tags' => $link->tags
                ->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
        ];
    }
}
