<?php

namespace App\Models;

use App\Concerns\HasCurrentUserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class Group extends Model implements Searchable
{
    use HasCurrentUserScope, HasFactory;

    protected $fillable = [
        'title',
        'user_id',
        'parent_group_id',
        'query_options',
    ];

    protected $casts = [
        'query_options' => 'array',
    ];

    public string $searchableType = 'Groups';

    /**
     * The query option keys, in the order the rule editor presents them.
     */
    public const TAG_RULE_KEYS = [
        'andTags' => 'containsTagsAnd',
        'orTags' => 'containsTagsOr',
        'notTags' => 'containsTagsNot',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return array_merge(
            [
                'title' => ['required', 'string', 'min:3', 'max:255'],
                'parentGroupId' => [
                    'nullable',
                    'integer',
                    Rule::exists('groups', 'id')->where('user_id', Auth::id()),
                ],
            ],
            static::tagRuleRules(),
        );
    }

    /**
     * A rule may only name a tag the current user already has links under —
     * a rule pointing at someone else's tag would silently match nothing, and
     * one naming a tag that does not exist cannot ever match.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function tagRuleRules(): array
    {
        $ownedTagIds = Tag::filterByCurrentUser()->pluck('id')->all();

        $rules = [];

        foreach (array_keys(static::TAG_RULE_KEYS) as $field) {
            $rules[$field] = ['array'];
            $rules[$field.'.*'] = ['integer', Rule::in($ownedTagIds)];
        }

        return $rules;
    }

    /**
     * Get all of the links that are assigned this group.
     */
    public function directLinks(): MorphToMany
    {
        return $this->morphedByMany(Link::class, 'groupable');
    }

    /**
     * The links this collection holds: the ones added to it by hand, plus the
     * ones its tag rules match, minus anything carrying an excluded tag.
     *
     * This is a query builder rather than a relation — the tag rules are not
     * expressible as one — so `$group->links` does not work; use `links()`.
     *
     * Ownership is filtered here, on the group's own `user_id`, rather than
     * through `filterByCurrentUser()`. The public share page runs this query
     * with nobody logged in, and an `Auth::id()` of `null` there would have
     * matched no rows at best and leaked another user's tagged links at worst.
     */
    public function links(): Builder
    {
        $andTags = $this->getAndTags();
        $orTags = $this->getOrTags();
        $notTags = $this->getNotTags();

        return Link::query()
            ->where('links.user_id', $this->user_id)
            ->where(function (Builder $query) use ($andTags, $orTags): void {
                $query->whereHas('groups', fn (Builder $groups) => $groups->whereKey($this->id));

                if ($andTags === [] && $orTags === []) {
                    return;
                }

                $query->orWhere(function (Builder $matches) use ($andTags, $orTags): void {
                    if ($andTags !== []) {
                        $matches->withAllTags($andTags);
                    }

                    if ($orTags !== []) {
                        $matches->withAnyTags($orTags);
                    }
                });
            })
            ->when($notTags, fn (Builder $query, array $notTags) => $query->withoutTags($notTags));
    }

    /**
     * Get all child groups.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_group_id');
    }

    /**
     * Get the group's public link.
     */
    public function publicLink(): MorphOne
    {
        return $this->morphOne(PublicLink::class, 'public_linkable');
    }

    public function getSearchResult(): SearchResult
    {
        $url = route('groups.show', $this->id);

        return new SearchResult(
            $this,
            $this->title,
            $url
        );
    }

    public function updateLinksCount(): int
    {
        return $this->links_count = $this->links()->count();
    }

    /**
     * @return array<int, Tag>
     */
    public function getOrTags(): array
    {
        return $this->getQueryOption('containsTagsOr');
    }

    /**
     * @return array<int, Tag>
     */
    public function getAndTags(): array
    {
        return $this->getQueryOption('containsTagsAnd');
    }

    /**
     * @return array<int, Tag>
     */
    public function getNotTags(): array
    {
        return $this->getQueryOption('containsTagsNot');
    }

    /**
     * Every tag id named by any of the three rules.
     *
     * @return array<int, int>
     */
    public function ruleTagIds(): array
    {
        $queryOptions = $this->query_options ?? [];

        $ids = [];

        foreach (static::TAG_RULE_KEYS as $key) {
            $ids = array_merge($ids, $queryOptions[$key] ?? []);
        }

        return array_values(array_unique($ids));
    }

    /**
     * The ids of every collection nested under this one, at any depth.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->groups()->get() as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * Resolve a rule's tag ids to models in one query.
     *
     * A tag named by a rule can disappear — `TagController::destroy` prunes the
     * rules it knows about, but a rule written before that guard, or one left
     * behind by a direct database change, can still name a row that is gone.
     * Resolving each id on its own put a `null` in the list, which crashed both
     * the group page and `withAnyTags()`.
     *
     * @return array<int, Tag>
     */
    protected function getQueryOption(string $queryOption): array
    {
        $ids = $this->query_options[$queryOption] ?? [];

        if ($ids === []) {
            return [];
        }

        return Tag::whereIn('id', $ids)->get()->all();
    }
}
