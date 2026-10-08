<?php

namespace App\Models;

use App\Casts\VectorCast;
use Database\Factories\ContentChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chunk of a link snapshot's `content_text`, embedded for semantic
 * search.
 *
 * Rebuildable from `LinkSnapshot::content_text` alone: chunks and their
 * embeddings never need a refetch, only re-chunking and re-embedding.
 */
class ContentChunk extends Model
{
    /** @use HasFactory<ContentChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'link_id',
        'link_snapshot_id',
        'ordinal',
        'text',
        'token_count',
        'embedding',
        'embedding_model',
    ];

    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'token_count' => 'integer',
            'embedding' => VectorCast::class,
        ];
    }

    /**
     * The link this chunk belongs to.
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class)->withTrashed();
    }

    /**
     * The snapshot this chunk was cut from.
     */
    public function linkSnapshot(): BelongsTo
    {
        return $this->belongsTo(LinkSnapshot::class);
    }
}
