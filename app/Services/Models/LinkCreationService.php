<?php

namespace App\Services\Models;

use App\Enums\LinkSource;
use App\Jobs\FetchLinkMetadataJob;
use App\Models\Link;
use App\Models\User;

class LinkCreationService
{
    public function create(User $user, string $url, LinkSource $source, ?string $title = null): Link
    {
        $link = Link::make();
        $link->link = $url;
        $link->title = $title;
        $link->user_id = $user->id;
        $link->source = $source;

        $link->save();

        FetchLinkMetadataJob::dispatch($link);

        return $link;
    }
}
