<?php

namespace App\Scraping\Drivers;

use App\Enums\ExtractionStatus;
use App\Scraping\Contracts\ContentExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\ReadabilityParser;
use App\Scraping\UrlGuard;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renders a page with a headless Chrome before parsing it, for the pages
 * `http_readability` gets back nothing but a JavaScript app shell for.
 *
 * There is no local Chrome on most hosts, so {@see self::supports()} is
 * false unless one has been configured; `App\Scraping\ScrapingManager`
 * (task 1.3) skips a driver whose supports() says no rather than trying it
 * and failing.
 */
final class BrowsershotExtractor implements ContentExtractor
{
    private const NAME = 'browsershot';

    public function supports(string $url): bool
    {
        return filled(config('scraping.browsershot.chrome_path'))
            || filled(config('scraping.browsershot.node_binary'));
    }

    public function extract(string $url): ExtractionResult
    {
        if (($error = UrlGuard::check($url)) !== null) {
            return ExtractionResult::failure(ExtractionStatus::Blocked, self::NAME, $error);
        }

        try {
            $browsershot = Browsershot::url($url)->timeout(30);

            if (filled($chromePath = config('scraping.browsershot.chrome_path'))) {
                $browsershot->setChromePath($chromePath);
            }

            if (filled($nodeBinary = config('scraping.browsershot.node_binary'))) {
                $browsershot->setNodeBinary($nodeBinary);
            }

            $html = $browsershot->bodyHtml();
        } catch (Throwable $exception) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage(), [
                'transient' => true,
            ]);
        }

        try {
            $parsed = ReadabilityParser::parse($html, $url);
        } catch (Throwable $exception) {
            // ReadabilityParser wraps a third-party library fed the page's
            // own (attacker-controlled) HTML; a driver must never throw,
            // whatever that library does with a malformed page.
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage());
        }

        if ($parsed === null) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, 'Unable to extract article content');
        }

        return ExtractionResult::ok(self::NAME, $parsed['text'], [
            'finalUrl' => $url,
            'title' => $parsed['title'],
            'author' => $parsed['author'],
            'publishedAt' => $parsed['published_at'],
        ]);
    }
}
