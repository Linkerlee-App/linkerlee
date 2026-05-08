<?php

namespace App\Models;

use App\Concerns\HasCurrentUserScope;
use Illuminate\Database\Eloquent\Builder;
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

    public string $searchableType = 'Tags';

    public function getSearchResult(): SearchResult
    {
        $url = route('links.index', ['tags' => $this->name]);

        return new SearchResult(
            $this,
            $this->name,
            $url
        );
    }
}
