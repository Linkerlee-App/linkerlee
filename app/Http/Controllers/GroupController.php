<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatchGroupRulesRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Link;
use App\Models\PublicLink;
use App\Services\Models\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
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
        $groups = Group::orderBy('title')
            ->filterByCurrentUser()
            ->withCount(['groups'])
            ->get();

        $ruleTags = $this->groupService->ruleTagsFor($groups);

        return Inertia::render('Groups/Index', [
            'groups' => $groups->map(fn (Group $group) => [
                'id' => $group->id,
                'title' => $group->title,
                'parentGroupId' => $group->parent_group_id,
                'linksCount' => $group->links_count,
                'childGroupsCount' => $group->groups_count,
                ...$this->groupService->presentRules($group, $ruleTags),
            ]),
            'allTags' => TagController::getAllTags(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $group = Group::make();

        $group->title = $validated['title'];
        $group->parent_group_id = $validated['parentGroupId'] ?? null;
        $group->user_id = Auth::id();

        $group->query_options = $this->queryOptionsFrom($validated);

        $group->save();

        // The count is of the links the collection holds, and a rule-matched
        // link is only found once the row it is counted against exists.
        $group->updateLinksCount();
        $group->save();

        return Redirect::route('groups.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $groupId): Response|RedirectResponse
    {
        $group = Group::filterByCurrentUser()->find($groupId);
        $searchString = Request::get('search') ?? '';
        $filteredTags = $this->requestedTagNames();
        $showUntaggedOnly = Request::boolean('untaggedOnly');

        if ($group === null) {
            return Redirect::route('home');
        }

        $allGroups = Group::orderBy('title')->filterByCurrentUser()->get();

        return Inertia::render('SingleGroup/Index', [
            'group' => [
                'title' => $group->title,
                'id' => $group->id,
                'parentGroupId' => $group->parent_group_id,
                ...$this->groupService->presentRules($group, $this->groupService->ruleTagsFor(collect([$group]))),
            ],
            'links' => Inertia::scroll(fn () => $group->links()
                ->orderBy('links.created_at', 'desc')
                ->filterLinks($searchString, $filteredTags, $showUntaggedOnly)
                ->cursorPaginate(20)
                ->through(fn (Link $link) => [
                    'title' => $link->title,
                    'link' => $link->link,
                    'id' => $link->id,
                ])),
            'publicLink' => (object) [
                'id' => $group->publicLink?->id,
                'link' => $group->publicLink?->getLink(),
            ],
            'searchString' => $searchString,
            'filteredTags' => $filteredTags ? TagController::getTagsByNames($filteredTags) : [],
            'showUntaggedOnly' => $showUntaggedOnly,
            'allTags' => TagController::getAllTags(),
            'allGroups' => $allGroups->map(fn (Group $candidate) => [
                'id' => $candidate->id,
                'title' => $candidate->title,
                'parentGroupId' => $candidate->parent_group_id,
            ]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $validated = $request->validated();

        $group->title = $validated['title'];
        $group->parent_group_id = $validated['parentGroupId'] ?? null;
        $group->query_options = $this->queryOptionsFrom($validated);

        $group->updateLinksCount();

        $group->save();

        return Redirect::back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group): RedirectResponse
    {
        abort_unless($group->user_id === Auth::id(), 404);

        /** @var PublicLink $publicLink */
        if ($publicLink = $group->publicLink) {
            $publicLink->delete();
        }

        $group->delete();

        return Redirect::route('groups.index');
    }

    /**
     * Count the links a set of rules would gather, so the rule editor can say
     * so before the collection is saved.
     */
    public function matchCount(MatchGroupRulesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $group = Group::make();
        $group->user_id = Auth::id();
        $group->query_options = $this->queryOptionsFrom($validated);

        // Without an id the query finds no hand-added members, which is right
        // for a collection that does not exist yet and wrong for one being
        // edited — there, its existing members count towards the total.
        if (isset($validated['groupId'])) {
            $group->id = $validated['groupId'];
        }

        return response()->json(['count' => $group->links()->count()]);
    }

    /**
     * Map the request's rule fields onto the stored `query_options` keys.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, int>>
     */
    protected function queryOptionsFrom(array $validated): array
    {
        $queryOptions = [];

        foreach (Group::TAG_RULE_KEYS as $field => $key) {
            $queryOptions[$key] = $validated[$field] ?? [];
        }

        return $this->groupService->cleanupQueryOptions($queryOptions);
    }

    protected function getAllParentGroups(Group $group): ?array
    {
        $parentGroups = [];
        $currentGroupInLoop = $group;

        while (($currentGroupInLoop = $this->getParentGroup($currentGroupInLoop)) !== null) {
            $parentGroups[] = (object) [
                'title' => $currentGroupInLoop->title,
                'link' => route('groups.show', $currentGroupInLoop->id, absolute: false),
            ];
        }

        return array_reverse($parentGroups);
    }

    protected function getParentGroup(Group $group): ?Group
    {
        if ($group->parent_group_id === null) {
            return null;
        }

        return Group::filterByCurrentUser()->find($group->parent_group_id);
    }

    /**
     * Returns all groups for the current user.
     */
    public function getAllGroups()
    {
        return Group::orderBy('title')
            ->filterByCurrentUser()
            ->withCount(['groups'])
            ->get()
            ->transform(fn (Group $group) => [
                'id' => $group->id,
                'title' => $group->title,
                'parentGroupId' => $group->parent_group_id,
                'childGroupsCount' => $group->groups_count,
                'linksCount' => $group->links_count,
            ]);
    }
}
