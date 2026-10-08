<?php

use App\Scraping\Drivers\BrowsershotExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('scraping.browsershot.allow_unguarded', true);
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

test('supports is false with both paths configured until unguarded chrome is explicitly allowed', function () {
    config()->set('scraping.browsershot.chrome_path', '/usr/bin/chromium');
    config()->set('scraping.browsershot.node_binary', '/usr/local/bin/node');
    config()->set('scraping.browsershot.allow_unguarded', false);

    expect((new BrowsershotExtractor)->supports('https://example.com'))->toBeFalse();
});

test('allow_unguarded must be exactly true, not merely truthy', function () {
    config()->set('scraping.browsershot.chrome_path', '/usr/bin/chromium');
    config()->set('scraping.browsershot.allow_unguarded', 'yes');

    expect((new BrowsershotExtractor)->supports('https://example.com'))->toBeFalse();
});

test('unguarded chrome is off by default', function () {
    $config = require config_path('scraping.php');

    expect($config['browsershot']['allow_unguarded'])->toBeFalse();
});
