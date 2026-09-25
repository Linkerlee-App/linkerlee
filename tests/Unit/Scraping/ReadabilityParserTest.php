<?php

use App\Scraping\ReadabilityParser;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function scrapingFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/scraping/{$name}"));
}

test('parses the article body, title, author and published date', function () {
    $result = ReadabilityParser::parse(scrapingFixture('article.html'), 'https://example.com/article');

    expect($result)->not->toBeNull()
        ->and($result['title'])->toBe('The Quiet Rise of Rooftop Beekeeping')
        ->and($result['author'])->toBe('Jordan Ellis')
        ->and($result['published_at'])->not->toBeNull()
        ->and($result['published_at']->toIso8601String())->toContain('2024-03-15')
        ->and($result['text'])->toContain('rooftop apiary')
        ->and($result['text'])->not->toContain('Copyright 2024');

    $wordCount = count(preg_split('/\s+/u', trim($result['text']), -1, PREG_SPLIT_NO_EMPTY));

    expect($wordCount)->toBeGreaterThan(50);
});

test('returns a low word count body for a javascript app shell without throwing', function () {
    $result = ReadabilityParser::parse(scrapingFixture('js-shell.html'), 'https://example.com/app');

    expect($result)->not->toBeNull();

    $wordCount = count(preg_split('/\s+/u', trim($result['text']), -1, PREG_SPLIT_NO_EMPTY));

    expect($wordCount)->toBeLessThan(50);
});

test('converts a latin-1 page to valid utf-8 text', function () {
    $result = ReadabilityParser::parse(scrapingFixture('latin1.html'), 'https://example.com/le-cafe');

    expect($result)->not->toBeNull()
        ->and(mb_check_encoding($result['text'], 'UTF-8'))->toBeTrue()
        ->and($result['text'])->toContain('café')
        ->and($result['text'])->toContain('déjeuner');
});

test('returns null for html with no usable body', function () {
    expect(ReadabilityParser::parse('<html><head><title>Empty</title></head></html>', 'https://example.com/empty'))
        ->toBeNull();
});

test('a bogus meta charset does not throw and still returns a result', function () {
    $html = '<html><head><meta charset="x-bogus"><title>Bogus</title></head>'
        .'<body><article><p>'.str_repeat('word ', 60).'</p></article></body></html>';

    $result = ReadabilityParser::parse($html, 'https://example.com/bogus-charset');

    expect($result)->not->toBeNull()
        ->and($result['text'])->toContain('word');
});
