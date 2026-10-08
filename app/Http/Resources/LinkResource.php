<?php

namespace App\Http\Resources;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Link
 */
class LinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
            'is_favorite' => $this->is_favorite,
            'rating' => $this->rating,
            'read_at' => $this->read_at?->toISOString(),
            'source' => $this->source?->value,
            'favicon_url' => $this->favicon_url,
            'preview_image_url' => $this->preview_image_url,
            'extraction_status' => $this->extraction_status?->value,
            'extraction_error' => $this->extraction_error,
            'extracted_at' => $this->extracted_at?->toISOString(),
            'health_status' => $this->health_status?->value,
            'redirect_url' => $this->redirect_url,
            'last_checked_at' => $this->getLastCheckedAtForHumansAttribute(true),
            'content_changed_at' => $this->getContentChangedAtForHumansAttribute(true),
            'tags' => $this->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
            'tag_ids' => $this->tags->pluck('id')->values(),
            'linkGroups' => $this->groups->sortBy('title')->values()->map(fn ($group) => ['id' => $group->id, 'title' => $group->title]),
            'group_ids' => $this->groups->pluck('id')->values(),
            'created_at' => $this->getCreatedAtForHumansAttribute(),
            'created_at_with_time' => $this->getCreatedAtForHumansAttribute(true),
        ];
    }
}
