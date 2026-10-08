<?php

namespace App\Models;

use Database\Factories\LinkSnapshotFactory;
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
}
