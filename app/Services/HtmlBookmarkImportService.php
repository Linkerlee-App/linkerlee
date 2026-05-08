<?php

namespace App\Services;

class HtmlBookmarkImportService
{
    public function extractData(string $html): array
    {
        $links = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $anchorNodes = $dom->getElementsByTagName('a');

        foreach ($anchorNodes as $anchor) {
            $href = $anchor->getAttribute('href');

            if (empty($href) || ! filter_var($href, FILTER_VALIDATE_URL)) {
                continue;
            }

            $links[] = [
                'title' => trim($anchor->textContent) ?: null,
                'link' => $href,
                'tags' => [],
            ];
        }

        return ['links' => $links];
    }
}
