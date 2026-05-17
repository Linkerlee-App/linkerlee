<?php

namespace App\Http\Controllers\ApiControllers;

use App\Helpers\WebpageData;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestTagController extends Controller
{
    private const MAX_SUGGESTIONS = 10;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('create')) {
            abort(403);
        }

        $validated = $request->validate([
            'link' => ['required', 'url'],
        ]);

        $text = WebpageData::getSearchableText($validated['link']);

        $tags = Tag::filterByCurrentUser()
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($text === null || $tags->isEmpty()) {
            return response()->json([]);
        }

        $matches = $tags->filter(function ($tag) use ($text) {
            $name = strtolower(trim((string) $tag->name));

            if ($name === '') {
                return false;
            }

            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u';

            return preg_match($pattern, $text) === 1;
        })->take(self::MAX_SUGGESTIONS)->values();

        return response()->json(
            $matches->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->all()
        );
    }
}
