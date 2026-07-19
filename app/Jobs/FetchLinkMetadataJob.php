<?php

namespace App\Jobs;

use App\Helpers\WebpageData;
use App\Models\Link;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class FetchLinkMetadataJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Link $link)
    {
        //
    }

    /**
     * Fetch the bookmarked page once and store its metadata on the link:
     * title/description (only when the user left them blank), favicon,
     * preview image and the searchable page text used for full-text search.
     */
    public function handle(): void
    {
        $metadata = WebpageData::getPageMetadata($this->link->link);

        if ($metadata === null) {
            return;
        }

        if (empty($this->link->title)) {
            $this->link->title = $metadata['title'];
        }

        if (empty($this->link->description)) {
            $this->link->description = $metadata['description'];
        }

        $this->link->favicon_url = $metadata['favicon_url'];
        $this->link->preview_image_url = $metadata['preview_image_url'];
        $this->link->page_text = $metadata['page_text'];
        $this->link->metadata_fetched_at = now();

        $this->link->save();
    }
}
