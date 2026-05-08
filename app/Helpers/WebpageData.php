<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class WebpageData
{
    public static function getWebPageTitle(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();

            $titleNodes = $dom->getElementsByTagName('title');

            if ($titleNodes->length > 0) {
                return trim($titleNodes->item(0)->textContent) ?: null;
            }
        } catch (\Throwable) {
            // Silently fail — URL may be unreachable or return invalid HTML
        }

        return null;
    }
}
