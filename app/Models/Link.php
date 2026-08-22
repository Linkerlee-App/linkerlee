<?php

namespace App\Models;

use App\Concerns\HasCurrentUserScope;
use App\Enums\LinkSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use Spatie\Tags\HasTags;

class Link extends Model implements Searchable
{
    use HasCurrentUserScope,
        HasFactory,
        HasTags,
        SoftDeletes;

    /**
     * Replaces HasTags::bootHasTags to keep tags through a soft delete.
     *
     * The package's `deleted` hook detaches unconditionally, so archiving a link
     * — which promises the link can be brought back — silently stripped its
     * tags, and it came back bare. The `created` half is reproduced verbatim
     * because overriding the boot method replaces it wholesale.
     *
     * On a force delete the tags still have to go, or the taggables rows outlive
     * the row they point at.
     */
    public static function bootHasTags(): void
    {
        static::created(function (Model $taggableModel) {
            if (count($taggableModel->queuedTags) === 0) {
                return;
            }

            $taggableModel->attachTags($taggableModel->queuedTags);

            $taggableModel->queuedTags = [];
        });

        static::deleted(function (Model $deletedModel) {
            if (! $deletedModel->forceDeleting) {
                return;
            }

            $deletedModel->detachTags($deletedModel->tags()->get());
        });
    }

    protected $fillable = [
        'title',
        'description',
        'link',
        'is_favorite',
        'rating',
        'read_at',
        'favicon_url',
        'preview_image_url',
        'page_text',
        'metadata_fetched_at',
    ];

    /**
     * Maximum length of the `link`, `favicon_url` and `preview_image_url` columns.
     */
    public const MAX_URL_LENGTH = 2048;

    /**
     * Maximum length of the `title` column.
     */
    public const MAX_TITLE_LENGTH = 255;

    public string $searchableType = 'Links';

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'rating' => 'integer',
            'read_at' => 'datetime',
            'metadata_fetched_at' => 'datetime',
            'source' => LinkSource::class,
        ];
    }

    public static function rules($linkFromRequest, $link = ''): array
    {
        return [
            'title' => 'string|min:2|nullable',
            'description' => 'string|nullable',
            'link' => [
                'url',
                'required',
                'max:'.self::MAX_URL_LENGTH,
                $link !== $linkFromRequest ? Rule::unique(Link::class, 'link')->where(fn ($query) => $query->where('user_id', \Auth::id())) : '',
            ],
        ];
    }

    /**
     * Page titles are often longer than the column allows, so keep what fits
     * instead of failing the whole save.
     */
    protected function title(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::limit($value, self::MAX_TITLE_LENGTH, ''));
    }

    /**
     * A URL that does not fit the column (e.g. an inline data URI) is dropped
     * rather than truncated, since a partial URL is unusable.
     */
    protected function faviconUrl(): Attribute
    {
        return Attribute::set(fn (?string $value) => self::urlThatFits($value));
    }

    protected function previewImageUrl(): Attribute
    {
        return Attribute::set(fn (?string $value) => self::urlThatFits($value));
    }

    protected static function urlThatFits(?string $value): ?string
    {
        if ($value === null || mb_strlen($value) > self::MAX_URL_LENGTH) {
            return null;
        }

        return $value;
    }

    /**
     * Get all groups for the link.
     */
    public function groups(): MorphToMany
    {
        return $this->morphToMany(Group::class, 'groupable');
    }

    /**
     * Get all group IDs for the link.
     */
    public function groupIds(): array
    {
        return $this->groups()->pluck('id')->toArray();
    }

    /**
     * Get all tag IDs for the link.
     */
    public function tagIds(): array
    {
        return $this->tags()->pluck('id')->toArray();
    }

    public function getCreatedAtForHumansAttribute(bool $withTime = false): string
    {
        if ($withTime) {
            return $this->created_at->format('d.m.Y H:i:s');
        }

        return $this->created_at->format('d.m.Y');
    }

    public function getUpdatedAtForHumansAttribute(bool $withTime = false): string
    {
        if ($withTime) {
            return $this->updated_at->format('d.m.Y H:i:s');
        }

        return $this->updated_at->format('d.m.Y');
    }

    /**
     * Several tag names narrow the listing instead of widening it: a link has
     * to carry every one of them. Picking a second tag is how the user asks
     * for "both of these", and an OR there only ever buried the first pick.
     *
     * Sources go the other way: they are mutually exclusive per link, so
     * several picked at once can only mean "any of these".
     *
     * @param  array<int, string>  $filteredTags
     * @param  array<int, LinkSource>  $filteredSources
     */
    public function scopeFilterLinks(Builder $query, string $searchString, array $filteredTags = [], bool|string $showUntaggedOnly = false, bool|string $showUnreadOnly = false, bool|string $showFavoritesOnly = false, array $filteredSources = []): Builder
    {
        return $query
            ->when($searchString, fn ($q) => $q->where(fn ($q) => $this->applySearchString($q, $searchString)))
            ->when($filteredTags, fn ($q) => $q->withAllTags($filteredTags))
            ->when($showUntaggedOnly, fn ($q) => $q->whereDoesntHave('tags'))
            ->when($showUnreadOnly, fn ($q) => $q->whereNull('links.read_at'))
            ->when($showFavoritesOnly, fn ($q) => $q->where('links.is_favorite', true))
            ->when($filteredSources, fn ($q) => $q->whereIn('links.source', $filteredSources));
    }

    /**
     * Match the search string against title/URL and, where the database
     * supports it, against the stored page content via a full-text index.
     */
    protected function applySearchString(Builder $query, string $searchString): Builder
    {
        $query
            ->where('links.title', 'LIKE', "%{$searchString}%")
            ->orWhere('links.link', 'LIKE', "%{$searchString}%")
            ->orWhere('links.description', 'LIKE', "%{$searchString}%");

        if ($query->getConnection()->getDriverName() === 'mysql') {
            return $query->orWhereFullText(['links.title', 'links.description', 'links.page_text'], $searchString);
        }

        return $query->orWhere('links.page_text', 'LIKE', "%{$searchString}%");
    }

    public function getSearchResult(): SearchResult
    {
        $url = route('links.show', $this->id);

        return new SearchResult(
            $this,
            $this->title,
            $url
        );
    }
}
