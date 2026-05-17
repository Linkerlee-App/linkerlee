<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class WebpageData
{
    public static function getWebPageTitle(string $url): ?string
    {
        $dom = self::loadDom($url);

        if ($dom === null) {
            return null;
        }

        $titleNodes = $dom->getElementsByTagName('title');

        if ($titleNodes->length > 0) {
            return trim($titleNodes->item(0)->textContent) ?: null;
        }

        return null;
    }

    /**
     * Extract text useful for tag-matching: <title>, meta description/keywords,
     * Open Graph metadata, and h1/h2/h3 headings. Returns a single lowercased
     * string with words separated by spaces, or null if the page can't be loaded.
     */
    public static function getSearchableText(string $url): ?string
    {
        $dom = self::loadDom($url);

        if ($dom === null) {
            return null;
        }

        $parts = [];

        foreach ($dom->getElementsByTagName('title') as $node) {
            $parts[] = $node->textContent;
        }

        foreach ($dom->getElementsByTagName('meta') as $node) {
            $name = strtolower((string) $node->getAttribute('name'));
            $property = strtolower((string) $node->getAttribute('property'));
            $content = (string) $node->getAttribute('content');

            if ($content === '') {
                continue;
            }

            if (in_array($name, ['description', 'keywords'], true) || str_starts_with($property, 'og:')) {
                $parts[] = $content;
            }
        }

        foreach (['h1', 'h2', 'h3'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $node) {
                $parts[] = $node->textContent;
            }
        }

        $text = strtolower(implode(' ', $parts));
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text) ?: null;
    }

    private static function loadDom(string $url): ?\DOMDocument
    {
        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();

            return $dom;
        } catch (\Throwable) {
            return null;
        }
    }
}
