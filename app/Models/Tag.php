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
     * Get all links tagged with this tag.
     */
    public function links(): MorphToMany
    {
        return $this->morphedByMany(Link::class, 'taggable');
    }

    /**
     * Order tags alphabetically by their translated name.
     *
     * `name` is a JSON column, and MySQL does not order one by the string it
     * holds, so `orderBy('name')` yields an order that looks arbitrary to the
     * user. The extracted value carries a binary collation on both drivers,
     * hence the `lower()` needed to keep the listing case-insensitive.
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
