<?php

use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Enrichment\Providers\FakeSummaryProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

afterEach(function () {
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

test('the fake summary provider returns a deterministic summary and records its calls', function () {
    $provider = new FakeSummaryProvider;

    expect($provider->summarize('Rooftop Beekeeping', 'Some text'))->toBe('Summary of Rooftop Beekeeping')
        ->and(FakeSummaryProvider::$calls)->toBe([
            ['title' => 'Rooftop Beekeeping', 'text' => 'Some text'],
        ]);
});

test('reset clears recorded summary calls', function () {
    (new FakeSummaryProvider)->summarize('Title', 'Text');
    FakeSummaryProvider::reset();

    expect(FakeSummaryProvider::$calls)->toBe([]);
});

test('the fake embedding provider is deterministic per text and matches the configured dimension', function () {
    config()->set('enrichment.embedding.ollama.dimensions', 16);

    $provider = new FakeEmbeddingProvider;

    $first = $provider->embed(['same text'])[0];
    $second = $provider->embed(['same text'])[0];
    $different = $provider->embed(['different text'])[0];

    expect($first)->toHaveCount(16)
        ->and($first)->toBe($second)
        ->and($first)->not->toBe($different);
});

test('reset clears recorded embedding calls', function () {
    (new FakeEmbeddingProvider)->embed(['text']);
    FakeEmbeddingProvider::reset();

    expect(FakeEmbeddingProvider::$calls)->toBe([]);
});
