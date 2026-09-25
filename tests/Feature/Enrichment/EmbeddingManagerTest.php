<?php

use App\Enrichment\EmbeddingManager;
use App\Enrichment\Providers\FakeEmbeddingProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    FakeEmbeddingProvider::reset();
});

afterEach(function () {
    FakeEmbeddingProvider::reset();
});

test('the manager is registered as a singleton', function () {
    expect(app(EmbeddingManager::class))->toBeInstanceOf(EmbeddingManager::class)
        ->and(app(EmbeddingManager::class))->toBe(app(EmbeddingManager::class));
});

test('an unknown driver name throws', function () {
    $manager = new EmbeddingManager(app());

    expect(fn () => $manager->driver('does-not-exist'))->toThrow(InvalidArgumentException::class);
});

test('provider() with no argument returns the configured default model', function () {
    config()->set('enrichment.embedding.driver', 'fake');

    $provider = (new EmbeddingManager(app()))->provider();

    expect($provider->model())->toBe('fake-embedding');
});

test('provider() with a model overrides the configured default for that call only', function () {
    config()->set('enrichment.embedding.driver', 'fake');

    $manager = new EmbeddingManager(app());

    $overridden = $manager->provider('a-one-off-model');
    $default = $manager->provider();

    expect($overridden->model())->toBe('a-one-off-model')
        ->and($default->model())->toBe('fake-embedding');
});
