<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

abstract class Controller
{
    /**
     * The tag names a listing should be filtered by.
     *
     * Tags are sent as repeated `tags[]` parameters so that a name containing
     * a comma survives the round trip; the older comma-separated form is still
     * accepted for links shared or bookmarked before that change.
     *
     * Every listing has to normalise the same way. The filter ANDs its names,
     * so a stray empty entry from a trailing comma resolves to no tag at all
     * and would empty the page instead of being ignored.
     *
     * @return array<int, string>
     */
    protected function requestedTagNames(): array
    {
        $requested = Request::get('tags');

        if (! is_array($requested)) {
            $requested = $requested === null || $requested === ''
                ? []
                : explode(',', (string) $requested);
        }

        return collect($requested)
            ->filter(fn ($name) => is_string($name))
            ->map(fn (string $name) => trim($name))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();
    }
}
