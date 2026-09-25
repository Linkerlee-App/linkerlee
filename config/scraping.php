<?php

use App\Scraping\Drivers\BrowsershotExtractor;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\Drivers\HttpReadabilityExtractor;
use App\Scraping\Drivers\JinaReaderExtractor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default extraction driver
    |--------------------------------------------------------------------------
    |
    | The driver used first when extracting article content from a saved
    | link. Adding a new engine is one class plus one line in "drivers"
    | below.
    |
    */

    'default' => env('SCRAPING_DRIVER', 'http_readability'),

    /*
    |--------------------------------------------------------------------------
    | Fallback drivers
    |--------------------------------------------------------------------------
    |
    | Tried in order, after the default driver, when it fails to produce
    | usable content. Empty in tests, so no fallback network call ever
    | happens there.
    |
    */

    'fallbacks' => array_filter(explode(',', env('SCRAPING_FALLBACKS', 'browsershot'))),

    'drivers' => [
        'http_readability' => HttpReadabilityExtractor::class,
        'browsershot' => BrowsershotExtractor::class,
        'jina' => JinaReaderExtractor::class,
        'fake' => FakeExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-domain driver overrides
    |--------------------------------------------------------------------------
    |
    | Some domains need a dedicated extractor (e.g. 'youtube.com' =>
    | 'youtube_transcript'). Empty until such a driver exists.
    |
    */

    'domains' => [
        // 'youtube.com' => 'youtube_transcript',
    ],

    'timeout' => (int) env('SCRAPING_TIMEOUT', 15),

    'max_bytes' => (int) env('SCRAPING_MAX_BYTES', 5 * 1024 * 1024),

    'user_agent' => env('SCRAPING_USER_AGENT', 'LinkerLeeBot/1.0 (+https://linkerlee.com)'),

    'min_word_count' => (int) env('SCRAPING_MIN_WORD_COUNT', 50),

    'browsershot' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    ],

    'jina' => [
        'api_key' => env('JINA_API_KEY'),
    ],

];
