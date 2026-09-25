<?php

namespace Tests\Support;

use App\Scraping\Contracts\ContentExtractor;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use Illuminate\Support\Str;

/**
 * A second test driver, independent of {@see FakeExtractor}, so a test can
 * register two distinct drivers in a fallback chain without one's canned
 * responses clobbering the other's.
 *
 * This duplicates FakeExtractor's small body rather than subclassing it:
 * FakeExtractor reads and writes `self::$responses`/`self::$calls`, and PHP
 * resolves `self::` on a static property against the class that declares
 * it, not the class actually called. A subclass would therefore share
 * FakeExtractor's static state instead of getting its own.
 */
final class SecondFakeExtractor implements ContentExtractor
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

        return ExtractionResult::ok('second_fake', str_repeat('word ', 200));
    }

    public function supports(string $url): bool
    {
        return true;
    }
}
