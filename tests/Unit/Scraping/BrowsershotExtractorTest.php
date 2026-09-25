<?php

use App\Scraping\Drivers\BrowsershotExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('supports is false when neither chrome_path nor node_binary is configured', function () {
    config()->set('scraping.browsershot.chrome_path', null);
    config()->set('scraping.browsershot.node_binary', null);

    expect((new BrowsershotExtractor)->supports('https://example.com'))->toBeFalse();
});

test('supports is true when chrome_path is configured', function () {
    config()->set('scraping.browsershot.chrome_path', '/usr/bin/chromium');
    config()->set('scraping.browsershot.node_binary', null);

    expect((new BrowsershotExtractor)->supports('https://example.com'))->toBeTrue();
});

test('supports is true when node_binary is configured', function () {
    config()->set('scraping.browsershot.chrome_path', null);
    config()->set('scraping.browsershot.node_binary', '/usr/local/bin/node');

    expect((new BrowsershotExtractor)->supports('https://example.com'))->toBeTrue();
});
