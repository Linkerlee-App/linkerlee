<?php

use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\Providers\OllamaEmbeddingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('enrichment.embedding.ollama.url', 'http://127.0.0.1:11434');
    config()->set('enrichment.embedding.ollama.model', 'nomic-embed-text');
    config()->set('enrichment.embedding.ollama.dimensions', 4);
    config()->set('enrichment.embedding.ollama.timeout', 60);
});

test('sends the api/embed request shape and maps embeddings back in order', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response([
            'embeddings' => [
                [0.1, 0.2, 0.3, 0.4],
                [0.5, 0.6, 0.7, 0.8],
            ],
        ], 200),
    ]);

    $vectors = (new OllamaEmbeddingProvider)->embed(['first chunk', 'second chunk']);

    expect($vectors)->toBe([
        [0.1, 0.2, 0.3, 0.4],
        [0.5, 0.6, 0.7, 0.8],
    ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://127.0.0.1:11434/api/embed'
            && $request->data() === [
                'model' => 'nomic-embed-text',
                'input' => ['first chunk', 'second chunk'],
            ];
    });
});

test('an empty list of texts never makes a request', function () {
    $vectors = (new OllamaEmbeddingProvider)->embed([]);

    expect($vectors)->toBe([]);

    Http::assertNothingSent();
});

test('a non-2xx response throws', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn () => (new OllamaEmbeddingProvider)->embed(['text']))
        ->toThrow(EnrichmentProviderException::class);
});

test('an embeddings count mismatched with the input throws', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response([
            'embeddings' => [[0.1, 0.2, 0.3, 0.4]],
        ], 200),
    ]);

    expect(fn () => (new OllamaEmbeddingProvider)->embed(['first', 'second']))
        ->toThrow(EnrichmentProviderException::class);
});

test('a missing embeddings key throws', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response([], 200),
    ]);

    expect(fn () => (new OllamaEmbeddingProvider)->embed(['text']))
        ->toThrow(EnrichmentProviderException::class);
});

test('a vector whose length does not match the configured dimensions throws', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response([
            // dimensions is configured to 4 in beforeEach(); this vector has 3.
            'embeddings' => [[0.1, 0.2, 0.3]],
        ], 200),
    ]);

    expect(fn () => (new OllamaEmbeddingProvider)->embed(['text']))
        ->toThrow(EnrichmentProviderException::class);
});

test('model, dimensions and withModel report the effective configuration', function () {
    $provider = new OllamaEmbeddingProvider;

    expect($provider->model())->toBe('nomic-embed-text')
        ->and($provider->dimensions())->toBe(4);

    $overridden = $provider->withModel('a-different-model');

    expect($overridden->model())->toBe('a-different-model')
        ->and($overridden)->not->toBe($provider);
});

test('probeDimensions reports the length the model really returns, whatever the config says', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3, 0.4, 0.5, 0.6]]], 200),
    ]);

    expect((new OllamaEmbeddingProvider)->probeDimensions())->toBe(6);

    Http::assertSent(fn (Request $request): bool => $request->data()['input'] === ['dimension probe']);
});

test('probeDimensions throws on a failed request', function () {
    Http::fake([
        '127.0.0.1:11434/api/embed' => Http::response('model not found', 404),
    ]);

    expect(fn () => (new OllamaEmbeddingProvider)->probeDimensions())
        ->toThrow(EnrichmentProviderException::class);
});
