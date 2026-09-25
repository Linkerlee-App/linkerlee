<?php

use App\Enums\ExtractionStatus;
use App\Scraping\Drivers\HttpReadabilityExtractor;
use App\Scraping\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    // The extractor calls UrlGuard first, which would otherwise do a real
    // DNS lookup for every fixture URL below.
    UrlGuard::$resolver = fn (string $host): array => ['93.184.216.34'];
});

afterEach(function () {
    UrlGuard::$resolver = null;
});

function scrapingFixtureBody(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/scraping/{$name}"));
}

/**
 * The literal below pins both ReadabilityParser's extraction of
 * article.html and ExtractionResult::normalize()'s output for it: if
 * either changes what text comes out, this hash is the thing that catches
 * it. It is not recomputed from the result, unlike the assertion this
 * replaced, which just hashed whatever came back and asserted it matched
 * itself.
 */
test('an article page is extracted with a content hash and a word count above 50', function () {
    Http::fake([
        'example.com/*' => Http::response(scrapingFixtureBody('article.html'), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'ETag' => '"abc123"',
            'Last-Modified' => 'Fri, 15 Mar 2024 09:00:00 GMT',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/article');

    expect($result->status)->toBe(ExtractionStatus::Ok)
        ->and($result->isOk())->toBeTrue()
        ->and($result->extractor)->toBe('http_readability')
        ->and($result->title)->toBe('The Quiet Rise of Rooftop Beekeeping')
        ->and($result->author)->toBe('Jordan Ellis')
        ->and($result->wordCount)->toBe(234)
        ->and($result->contentHash)->toBe('f34acaa9d166a8e3c12cf4d9da61a9c2d67c5be9395a3e78a90484597f860c98')
        ->and($result->etag)->toBe('"abc123"')
        ->and($result->lastModified)->toBe('Fri, 15 Mar 2024 09:00:00 GMT')
        ->and($result->httpStatus)->toBe(200);
});

test('a javascript app shell is extracted ok with a word count below 50', function () {
    Http::fake([
        'example.com/*' => Http::response(scrapingFixtureBody('js-shell.html'), 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/app');

    expect($result->status)->toBe(ExtractionStatus::Ok)
        ->and($result->wordCount)->toBeLessThan(50);
});

test('a 404 is a non-transient failure', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/missing');

    expect($result->status)->toBe(ExtractionStatus::Failed)
        ->and($result->transient)->toBeFalse()
        ->and($result->httpStatus)->toBe(404);
});

test('a 503 is a transient failure', function () {
    Http::fake([
        'example.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/down');

    expect($result->status)->toBe(ExtractionStatus::Failed)
        ->and($result->transient)->toBeTrue()
        ->and($result->httpStatus)->toBe(503);
});

test('a connection exception is a transient failure', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/unreachable');

    expect($result->status)->toBe(ExtractionStatus::Failed)
        ->and($result->transient)->toBeTrue();
});

test('a 403 is blocked', function () {
    Http::fake([
        'example.com/*' => Http::response('Forbidden', 403),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/forbidden');

    expect($result->status)->toBe(ExtractionStatus::Blocked)
        ->and($result->httpStatus)->toBe(403);
});

test('a 429 is blocked and transient', function () {
    Http::fake([
        'example.com/*' => Http::response('Too Many Requests', 429),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/rate-limited');

    expect($result->status)->toBe(ExtractionStatus::Blocked)
        ->and($result->transient)->toBeTrue();
});

test('a pdf content type is unsupported', function () {
    Http::fake([
        'example.com/*' => Http::response('%PDF-1.4 ...', 200, [
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/document.pdf');

    expect($result->status)->toBe(ExtractionStatus::Unsupported);
});

test('a body over max_bytes is unsupported instead of read in full', function () {
    config()->set('scraping.max_bytes', 1024);

    Http::fake([
        'example.com/*' => Http::response(str_repeat('a', 1024 * 1024), 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/huge');

    expect($result->status)->toBe(ExtractionStatus::Unsupported);
});

test('a latin-1 page is decoded to valid utf-8 text', function () {
    Http::fake([
        'example.com/*' => Http::response(scrapingFixtureBody('latin1.html'), 200, [
            'Content-Type' => 'text/html; charset=ISO-8859-1',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/le-cafe');

    expect($result->status)->toBe(ExtractionStatus::Ok)
        ->and(mb_check_encoding($result->contentText, 'UTF-8'))->toBeTrue()
        ->and($result->contentText)->toContain('café');
});

test('a private host is blocked before any request is made', function () {
    UrlGuard::$resolver = fn (string $host): array => ['10.0.0.5'];

    $result = (new HttpReadabilityExtractor)->extract('http://internal.example.test/');

    expect($result->status)->toBe(ExtractionStatus::Blocked);
});

test('supports always returns true', function () {
    expect((new HttpReadabilityExtractor)->supports('https://example.com'))->toBeTrue();
});

test('a redirect to a private host is blocked and the target is never requested', function () {
    Http::fake([
        'example.com/*' => Http::response('', 302, [
            'Location' => 'http://10.0.0.5/internal',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/redirect');

    expect($result->status)->toBe(ExtractionStatus::Blocked);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '10.0.0.5'));
});

test('a page with a bogus meta charset does not throw', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><head><meta charset="x-bogus"><title>Bogus</title></head><body><article><p>'
                .str_repeat('word ', 60).'</p></article></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/bogus-charset');

    expect($result->status)->toBeIn([ExtractionStatus::Ok, ExtractionStatus::Failed]);
});

test('a body that never finishes reading within the configured timeout fails as transient', function () {
    // A timeout of 0 makes the very next wall-clock check already past the
    // deadline, which reproduces a slow-drip server without an actual
    // sleep: the assertion still runs in well under a second. The body
    // needs to be bigger than one read chunk (8KB) so the deadline check
    // is reached at least once.
    config()->set('scraping.timeout', 0);

    Http::fake([
        'example.com/*' => Http::response(str_repeat('a', 20000), 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $result = (new HttpReadabilityExtractor)->extract('https://example.com/slow-drip');

    expect($result->status)->toBe(ExtractionStatus::Failed)
        ->and($result->transient)->toBeTrue()
        ->and($result->error)->toContain('deadline');
});

test('the request is forced onto ipv4, so it connects to the same addresses UrlGuard checked', function () {
    $sentOptions = null;

    Http::fake(function ($request, array $options) use (&$sentOptions) {
        $sentOptions = $options;

        return Http::response(scrapingFixtureBody('article.html'), 200, ['Content-Type' => 'text/html']);
    });

    (new HttpReadabilityExtractor)->extract('https://example.com/article');

    expect($sentOptions)->toHaveKey('force_ip_resolve', 'v4');
});
