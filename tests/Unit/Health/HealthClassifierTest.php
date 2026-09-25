<?php

use App\Enums\HealthStatus;
use App\Health\HealthClassifier;
use App\Models\LinkSnapshot;
use App\Scraping\ExtractionResult;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->classifier = new HealthClassifier;
});

describe('classifyResponse', function () {
    test('classifies a status code into a health status', function (int $status, ?string $originalHost, ?string $finalHost, bool $permanentRedirect, HealthStatus $expected) {
        expect($this->classifier->classifyResponse($status, $originalHost, $finalHost, $permanentRedirect))
            ->toBe($expected);
    })->with([
        '200 OK' => [200, 'example.com', 'example.com', false, HealthStatus::Ok],
        '204 No Content' => [204, 'example.com', 'example.com', false, HealthStatus::Ok],
        '299 edge of 2xx' => [299, 'example.com', 'example.com', false, HealthStatus::Ok],
        '301 to a different host' => [301, 'example.com', 'other.com', true, HealthStatus::Redirected],
        '308 to a different host' => [308, 'example.com', 'other.com', true, HealthStatus::Redirected],
        '301 to the same host' => [301, 'example.com', 'example.com', true, HealthStatus::Error],
        '301 www-stripped equal host' => [301, 'www.example.com', 'example.com', true, HealthStatus::Error],
        '301 www-stripped equal host, case-insensitive' => [301, 'WWW.Example.com', 'example.com', true, HealthStatus::Error],
        '302 (temporary) to a different host' => [302, 'example.com', 'other.com', false, HealthStatus::Error],
        '307 (temporary) to a different host' => [307, 'example.com', 'other.com', false, HealthStatus::Error],
        '301 with a null original host' => [301, null, 'other.com', true, HealthStatus::Error],
        '301 with a null final host' => [301, 'example.com', null, true, HealthStatus::Error],
        '404 Not Found' => [404, 'example.com', 'example.com', false, HealthStatus::Gone],
        '410 Gone' => [410, 'example.com', 'example.com', false, HealthStatus::Gone],
        '500 Internal Server Error' => [500, 'example.com', 'example.com', false, HealthStatus::Error],
        '503 Service Unavailable' => [503, 'example.com', 'example.com', false, HealthStatus::Error],
        '0 (DNS failure or timeout)' => [0, 'example.com', null, false, HealthStatus::Error],
        '429 Too Many Requests (any other status)' => [429, 'example.com', 'example.com', false, HealthStatus::Error],
    ]);
});

describe('isSoft404', function () {
    test('flags a title that matches a soft-404 pattern', function () {
        $result = ExtractionResult::ok('fake', 'Some perfectly ordinary body text.', ['title' => 'Error 404']);

        expect($this->classifier->isSoft404($result, null))->toBeTrue();
    });

    test('does not false-positive on a number that merely contains 404', function () {
        $result = ExtractionResult::ok('fake', 'Product SKU 4040 is back in stock today.', ['title' => 'Product SKU 4040']);

        expect($this->classifier->isSoft404($result, null))->toBeFalse();
    });

    test('flags short body text that matches a soft-404 pattern', function () {
        $result = ExtractionResult::ok('fake', 'Sorry, this page is no longer available.', ['title' => 'Oops']);

        expect($this->classifier->isSoft404($result, null))->toBeTrue();
    });

    test('does not flag a long article that merely mentions "not found" once', function () {
        $words = array_fill(0, 250, 'word');
        $words[100] = 'not';
        $words[101] = 'found';
        $body = implode(' ', $words);

        $result = ExtractionResult::ok('fake', $body, ['title' => 'A Long Investigative Piece']);

        expect($result->wordCount)->toBeGreaterThanOrEqual(200)
            ->and($this->classifier->isSoft404($result, null))->toBeFalse();
    });

    test('flags an 85% word-count drop against the latest snapshot', function () {
        $latest = (new LinkSnapshot)->forceFill(['word_count' => 1000]);
        $result = ExtractionResult::ok('fake', implode(' ', array_fill(0, 100, 'word')), ['title' => 'Still Here']);

        expect($this->classifier->isSoft404($result, $latest))->toBeTrue();
    });

    test('does not flag a smaller but not-drastic word-count drop', function () {
        $latest = (new LinkSnapshot)->forceFill(['word_count' => 1000]);
        $result = ExtractionResult::ok('fake', implode(' ', array_fill(0, 900, 'word')), ['title' => 'Still Here']);

        expect($this->classifier->isSoft404($result, $latest))->toBeFalse();
    });

    test('the word-count-drop check is a no-op without a latest snapshot', function () {
        $result = ExtractionResult::ok('fake', implode(' ', array_fill(0, 5, 'word')), ['title' => 'Still Here']);

        expect($this->classifier->isSoft404($result, null))->toBeFalse();
    });
});
