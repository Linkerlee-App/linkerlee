<?php

namespace App\Models;

use App\Concerns\HasCurrentUserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class Tag extends \Spatie\Tags\Tag implements Searchable
{
    use HasCurrentUserScope;

    public function scopeFilterByCurrentUser(Builder $query): Builder
    {
        return $query->whereIn('id',
            DB::table('taggables')
                ->select('tag_id')
                ->where('taggable_type', Link::class)
                ->whereIn('taggable_id', Link::filterByCurrentUser()->select('id'))
        );
    }

    /**
     * The tags in use by one named user's links, archived links included.
     *
     * Tag rows carry no owner of their own, so "a user's tags" can only ever
     * mean the ones hanging off that user's links. This differs from
     * `filterByCurrentUser` in two ways: it names the user instead of reading
     * the session, and it counts archived links, which keep their tags against
     * the day they are restored.
     */
    public function scopeFilterByUser(Builder $query, int $userId): Builder
    {
        return $query->whereIn('id',
            DB::table('taggables')
                ->select('tag_id')
                ->where('taggable_type', Link::class)
                ->whereIn('taggable_id', Link::withTrashed()->where('user_id', $userId)->select('id'))
        );
    }

    /**
     * Get all links tagged with this tag.
     */
    public function links(): MorphToMany
    {
        return $this->morphedByMany(Link::class, 'taggable');
    }

    /**
     * Order tags alphabetically by their translated name.
     *
     * `name` is a JSON column, so `orderBy('name')` orders by the JSON text
     * rather than by the name the user sees. The name is extracted with `->>`
     * and lowercased so the listing sorts case-insensitively.
     */
    public function scopeOrderByName(Builder $query): Builder
    {
        $name = $query->getQuery()->getGrammar()->wrap('name->'.static::getLocale());

        return $query->orderByRaw("lower({$name}) asc");
    }

    public string $searchableType = 'Tags';

    public function getSearchResult(): SearchResult
    {
        $url = route('links.index', ['tags' => [$this->name]]);

        return new SearchResult(
            $this,
            $this->name,
            $url
        );
    }
}
