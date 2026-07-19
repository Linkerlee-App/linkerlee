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
     * Fetch a page once and extract all metadata LinkerLee stores on a link.
     *
     * @return array{title: ?string, description: ?string, favicon_url: ?string, preview_image_url: ?string, page_text: ?string}|null
     */
    public static function getPageMetadata(string $url): ?array
    {
        $dom = self::loadDom($url);

        if ($dom === null) {
            return null;
        }

        return [
            'title' => self::extractTitle($dom),
            'description' => self::extractDescription($dom),
            'favicon_url' => self::extractFaviconUrl($dom, $url),
            'preview_image_url' => self::extractPreviewImageUrl($dom, $url),
            'page_text' => self::extractSearchableText($dom),
        ];
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

        return self::extractSearchableText($dom);
    }

    private static function extractSearchableText(\DOMDocument $dom): ?string
    {
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

    private static function extractTitle(\DOMDocument $dom): ?string
    {
        $titleNodes = $dom->getElementsByTagName('title');

        if ($titleNodes->length > 0) {
            return trim($titleNodes->item(0)->textContent) ?: null;
        }

        return null;
    }

    private static function extractDescription(\DOMDocument $dom): ?string
    {
        $description = null;

        foreach ($dom->getElementsByTagName('meta') as $node) {
            $name = strtolower((string) $node->getAttribute('name'));
            $property = strtolower((string) $node->getAttribute('property'));
            $content = trim((string) $node->getAttribute('content'));

            if ($content === '') {
                continue;
            }

            if ($name === 'description') {
                return $content;
            }

            if ($property === 'og:description') {
                $description = $description ?? $content;
            }
        }

        return $description;
    }

    private static function extractPreviewImageUrl(\DOMDocument $dom, string $pageUrl): ?string
    {
        foreach ($dom->getElementsByTagName('meta') as $node) {
            $property = strtolower((string) $node->getAttribute('property'));
            $content = trim((string) $node->getAttribute('content'));

            if ($property === 'og:image' && $content !== '') {
                return self::resolveUrl($content, $pageUrl);
            }
        }

        return null;
    }

    private static function extractFaviconUrl(\DOMDocument $dom, string $pageUrl): ?string
    {
        foreach ($dom->getElementsByTagName('link') as $node) {
            $rel = strtolower((string) $node->getAttribute('rel'));
            $href = trim((string) $node->getAttribute('href'));

            if ($href !== '' && in_array($rel, ['icon', 'shortcut icon', 'apple-touch-icon'], true)) {
                return self::resolveUrl($href, $pageUrl);
            }
        }

        $origin = self::origin($pageUrl);

        return $origin !== null ? "{$origin}/favicon.ico" : null;
    }

    private static function resolveUrl(string $candidate, string $pageUrl): ?string
    {
        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }

        $parts = parse_url($pageUrl);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            return "{$parts['scheme']}:{$candidate}";
        }

        $origin = self::origin($pageUrl);

        return $origin.'/'.ltrim($candidate, '/');
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}";
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
