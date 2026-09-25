<?php

namespace App\Scraping\Drivers;

use App\Scraping\Contracts\ContentExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\UrlGuard;
use Illuminate\Support\Str;

/**
 * The driver used everywhere in the test suite (`SCRAPING_DRIVER=fake` in
 * phpunit.xml), so no test ever makes a real network request. It never
 * calls {@see UrlGuard}: there is nothing to protect against
 * when nothing is actually fetched.
 */
final class FakeExtractor implements ContentExtractor
{
    /**
     * @var array<string, ExtractionResult|callable(string): ExtractionResult>
     */
    public static array $responses = [];

    /**
     * Every URL passed to {@see self::extract()}, in call order.
     *
     * @var list<string>
     */
    public static array $calls = [];

    /**
     * @param  ExtractionResult|callable(string): ExtractionResult  $response
     */
    public static function respondWith(string $urlPattern, ExtractionResult|callable $response): void
    {
        self::$responses[$urlPattern] = $response;
    }

    public static function reset(): void
    {
        self::$responses = [];
        self::$calls = [];
    }

    public function extract(string $url): ExtractionResult
    {
        self::$calls[] = $url;

        foreach (self::$responses as $pattern => $response) {
            if (! Str::is($pattern, $url)) {
                continue;
            }

            return $response instanceof ExtractionResult
                ? $response
                : $response($url);
        }

        return ExtractionResult::ok('fake', str_repeat('word ', 200));
    }

    public function supports(string $url): bool
    {
        return true;
    }
}
