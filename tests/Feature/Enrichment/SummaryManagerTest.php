<?php

use App\Enrichment\Providers\FakeSummaryProvider;
use App\Enrichment\SummaryManager;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    FakeSummaryProvider::reset();
});

afterEach(function () {
    FakeSummaryProvider::reset();
});

test('the manager is registered as a singleton', function () {
    expect(app(SummaryManager::class))->toBeInstanceOf(SummaryManager::class)
        ->and(app(SummaryManager::class))->toBe(app(SummaryManager::class));
});

test('an unknown driver name throws', function () {
    $manager = new SummaryManager(app());

    expect(fn () => $manager->driver('does-not-exist'))->toThrow(InvalidArgumentException::class);
});

test('provider() with no argument returns the configured default model', function () {
    config()->set('enrichment.summary.driver', 'fake');

    $provider = (new SummaryManager(app()))->provider();

    expect($provider->model())->toBe('fake-summary');
});

test('provider() with a model overrides the configured default for that call only', function () {
    config()->set('enrichment.summary.driver', 'fake');

    $manager = new SummaryManager(app());

    $overridden = $manager->provider('a-one-off-model');
    $default = $manager->provider();

    expect($overridden->model())->toBe('a-one-off-model')
        ->and($default->model())->toBe('fake-summary');
});
