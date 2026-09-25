<?php

use App\Enums\ExtractionStatus;
use App\Scraping\Drivers\JinaReaderExtractor;
use App\Scraping\UrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    UrlGuard::$resolver = fn (string $host): array => ['93.184.216.34'];
});

afterEach(function () {
    UrlGuard::$resolver = null;
});

test('supports requires an api key', function () {
    config()->set('scraping.jina.api_key', null);
    expect((new JinaReaderExtractor)->supports('https://example.com'))->toBeFalse();

    config()->set('scraping.jina.api_key', 'test-key');
    expect((new JinaReaderExtractor)->supports('https://example.com'))->toBeTrue();
});

test('maps a jina json response to an ok extraction result', function () {
    config()->set('scraping.jina.api_key', 'test-key');

    Http::fake([
        'r.jina.ai/*' => Http::response([
            'code' => 200,
            'status' => 20000,
            'data' => [
                'title' => 'Rooftop Beekeeping',
                'content' => str_repeat('word ', 80),
                'publishedTime' => '2024-03-15T09:00:00Z',
            ],
        ], 200),
    ]);

    $result = (new JinaReaderExtractor)->extract('https://example.com/article');

    expect($result->status)->toBe(ExtractionStatus::Ok)
        ->and($result->extractor)->toBe('jina')
        ->and($result->title)->toBe('Rooftop Beekeeping')
        ->and($result->wordCount)->toBe(80)
        ->and($result->publishedAt?->toIso8601String())->toContain('2024-03-15');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://r.jina.ai/https://example.com/article'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && str_contains($request->header('Accept')[0] ?? '', 'application/json');
    });
});

test('a 401 from jina is blocked', function () {
    config()->set('scraping.jina.api_key', 'test-key');

    Http::fake([
        'r.jina.ai/*' => Http::response('Unauthorized', 401),
    ]);

    $result = (new JinaReaderExtractor)->extract('https://example.com/article');

    expect($result->status)->toBe(ExtractionStatus::Blocked);
});

test('a 500 from jina is a transient failure', function () {
    config()->set('scraping.jina.api_key', 'test-key');

    Http::fake([
        'r.jina.ai/*' => Http::response('Internal Server Error', 500),
    ]);

    $result = (new JinaReaderExtractor)->extract('https://example.com/article');

    expect($result->status)->toBe(ExtractionStatus::Failed)
        ->and($result->transient)->toBeTrue();
});

test('a private host is blocked before any request is made', function () {
    config()->set('scraping.jina.api_key', 'test-key');

    UrlGuard::$resolver = fn (string $host): array => ['10.0.0.5'];

    $result = (new JinaReaderExtractor)->extract('http://internal.example.test/');

    expect($result->status)->toBe(ExtractionStatus::Blocked);
});
