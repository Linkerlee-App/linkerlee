<?php

namespace App\Models;

use Database\Factories\LinkSnapshotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One distinct version of a link's extracted article text.
 *
 * A new row is written only when the text's hash changes, so consecutive
 * snapshots of one link always differ. Summaries, chunks and embeddings are
 * derived from `content_text` alone and can be rebuilt without a refetch.
 */
class LinkSnapshot extends Model
{
    /** @use HasFactory<LinkSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'link_id',
        'extractor',
        'http_status',
        'final_url',
        'title',
        'author',
        'published_at',
        'content_text',
        'content_hash',
        'word_count',
        'summary',
        'summary_model',
        'metadata',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'word_count' => 'integer',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The link this text was extracted from.
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class)->withTrashed();
    }

    /**
     * Whether this is still the latest snapshot of a live link, re-read from
     * the database. A snapshot of a deleted or trashed link, or one that a
     * newer snapshot has superseded, is not worth enriching.
     */
    public function isCurrent(): bool
    {
        $link = Link::withTrashed()->find($this->link_id);

        return $link !== null
            && ! $link->trashed()
            && $link->latest_snapshot_id === $this->id;
    }

    /**
     * Scopes a query to current snapshots: those referenced by
     * `links.latest_snapshot_id` on a non-trashed link. The query-side
     * counterpart to {@see self::isCurrent()}, which checks one
     * already-loaded instance instead of filtering a query; the rebuild
     * commands (`linkerlee:resummarize`, `linkerlee:rechunk`,
     * `linkerlee:reembed`) all scope their work this way.
     *
     * @param  Builder<LinkSnapshot>  $query
     * @return Builder<LinkSnapshot>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('id', function ($query): void {
            $query->select('latest_snapshot_id')
                ->from('links')
                ->whereNotNull('latest_snapshot_id')
                ->whereNull('deleted_at');
        });
    }
}
