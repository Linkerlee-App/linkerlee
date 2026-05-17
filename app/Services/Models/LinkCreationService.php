<?php

namespace App\Services\Models;

use App\Helpers\WebpageData;
use App\Models\Link;
use App\Models\User;

class LinkCreationService
{
    public function create(User $user, string $url, ?string $title = null): Link
    {
        $link = Link::make();
        $link->link = $url;
        $link->title = $title;
        $link->user_id = $user->id;

        if (empty($link->title)) {
            $link->title = WebpageData::getWebPageTitle($url);
        }

        $link->save();

        return $link;
    }
}
