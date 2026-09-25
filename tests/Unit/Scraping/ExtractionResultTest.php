<?php

use App\Enums\ExtractionStatus;
use App\Scraping\ExtractionResult;
use Carbon\CarbonImmutable;

test('ok computes a sha256 hash and a unicode-aware word count from the normalized text', function () {
    $result = ExtractionResult::ok('fake', "  Hello   world  \n\n\n\nCafé  résumé  ");

    $expectedText = "Hello world\n\nCafé résumé";

    expect($result->status)->toBe(ExtractionStatus::Ok)
        ->and($result->extractor)->toBe('fake')
        ->and($result->contentText)->toBe($expectedText)
        ->and($result->contentHash)->toBe(hash('sha256', $expectedText))
        ->and($result->wordCount)->toBe(4)
        ->and($result->error)->toBeNull()
        ->and($result->transient)->toBeFalse()
        ->and($result->isOk())->toBeTrue();
});

test('ok carries through the optional attributes', function () {
    $publishedAt = CarbonImmutable::parse('2024-01-02T03:04:05Z');

    $result = ExtractionResult::ok('http_readability', 'Some article body.', [
        'httpStatus' => 200,
        'finalUrl' => 'https://example.com/final',
        'etag' => 'W/"abc"',
        'lastModified' => 'Mon, 01 Jan 2024 00:00:00 GMT',
        'title' => 'A Title',
        'author' => 'Jane Doe',
        'publishedAt' => $publishedAt,
    ]);

    expect($result->httpStatus)->toBe(200)
        ->and($result->finalUrl)->toBe('https://example.com/final')
        ->and($result->etag)->toBe('W/"abc"')
        ->and($result->lastModified)->toBe('Mon, 01 Jan 2024 00:00:00 GMT')
        ->and($result->title)->toBe('A Title')
        ->and($result->author)->toBe('Jane Doe')
        ->and($result->publishedAt)->toBe($publishedAt);
});

test('failure carries the status, the error and is never ok', function () {
    $result = ExtractionResult::failure(ExtractionStatus::Blocked, 'http_readability', 'private host', [
        'httpStatus' => 403,
    ]);

    expect($result->status)->toBe(ExtractionStatus::Blocked)
        ->and($result->extractor)->toBe('http_readability')
        ->and($result->error)->toBe('private host')
        ->and($result->httpStatus)->toBe(403)
        ->and($result->contentText)->toBeNull()
        ->and($result->transient)->toBeFalse()
        ->and($result->isOk())->toBeFalse();
});

test('failure is transient when the attrs say so', function () {
    $result = ExtractionResult::failure(ExtractionStatus::Failed, 'http_readability', 'HTTP 503', [
        'transient' => true,
    ]);

    expect($result->transient)->toBeTrue();
});

test('normalize trims each line, collapses horizontal whitespace and excess blank lines', function () {
    $input = "  Line one   with   spaces  \nLine two\n\n\n\nLine three\n\n\n";

    expect(ExtractionResult::normalize($input))
        ->toBe("Line one with spaces\nLine two\n\nLine three");
});
