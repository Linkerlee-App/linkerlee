<?php

use App\Enums\ExtractionStatus;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\ScrapingManager;
use Illuminate\Support\Facades\Http;
use Tests\Support\SecondFakeExtractor;

beforeEach(function () {
    Http::preventStrayRequests();

    FakeExtractor::reset();
    SecondFakeExtractor::reset();

    config()->set('scraping.drivers.second_fake', SecondFakeExtractor::class);
});

afterEach(function () {
    FakeExtractor::reset();
    SecondFakeExtractor::reset();
});

test('the manager is registered as a singleton', function () {
    expect(app(ScrapingManager::class))->toBeInstanceOf(ScrapingManager::class)
        ->and(app(ScrapingManager::class))->toBe(app(ScrapingManager::class));
});

test('an unknown driver name throws', function () {
    $manager = new ScrapingManager(app());

    expect(fn () => $manager->driver('does-not-exist'))->toThrow(InvalidArgumentException::class);
});

test('switching the default driver changes which driver runs', function () {
    config()->set('scraping.fallbacks', []);
    FakeExtractor::respondWith('*', ExtractionResult::ok('fake', str_repeat('word ', 200)));
    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    config()->set('scraping.default', 'fake');
    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->extractor)->toBe('fake');
    expect(SecondFakeExtractor::$calls)->toBe([]);

    config()->set('scraping.default', 'second_fake');
    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->extractor)->toBe('second_fake');
});

test('a failed primary falls back to the next driver', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', ['second_fake']);

    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'boom'));
    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->isOk())->toBeTrue()
        ->and($result->extractor)->toBe('second_fake');
});

test('an ok but thin primary falls back to a thicker fallback', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', ['second_fake']);
    config()->set('scraping.min_word_count', 50);

    FakeExtractor::respondWith('*', ExtractionResult::ok('fake', str_repeat('word ', 10)));
    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->extractor)->toBe('second_fake')
        ->and($result->wordCount)->toBe(200);
});

test('when every driver is thin, the best ok result wins', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', ['second_fake']);
    config()->set('scraping.min_word_count', 50);

    FakeExtractor::respondWith('*', ExtractionResult::ok('fake', str_repeat('word ', 10)));
    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 20)));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->isOk())->toBeTrue()
        ->and($result->extractor)->toBe('second_fake')
        ->and($result->wordCount)->toBe(20);
});

test('when all drivers fail, the first failure is returned', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', ['second_fake']);

    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'first boom'));
    SecondFakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Failed, 'second_fake', 'second boom'));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->isOk())->toBeFalse()
        ->and($result->extractor)->toBe('fake')
        ->and($result->error)->toBe('first boom');
});

test('a domain override beats the default driver, including subdomains', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', []);
    config()->set('scraping.domains', ['youtube.com' => 'second_fake']);

    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    $result = (new ScrapingManager(app()))->extractFor('https://m.youtube.com/watch?v=abc');

    expect($result->extractor)->toBe('second_fake')
        ->and(FakeExtractor::$calls)->toBe([])
        ->and(SecondFakeExtractor::$calls)->toBe(['https://m.youtube.com/watch?v=abc']);
});

test('domain matching ignores a leading www on either side', function () {
    config()->set('scraping.default', 'fake');
    config()->set('scraping.fallbacks', []);
    config()->set('scraping.domains', ['www.example.com' => 'second_fake']);

    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->extractor)->toBe('second_fake');
});

test('a driver whose supports() is false is skipped', function () {
    config()->set('scraping.default', 'jina');
    config()->set('scraping.fallbacks', ['second_fake']);
    config()->set('scraping.jina.api_key', null);

    SecondFakeExtractor::respondWith('*', ExtractionResult::ok('second_fake', str_repeat('word ', 200)));

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->extractor)->toBe('second_fake');
});

test('returns unsupported when no driver in the chain supports the url', function () {
    config()->set('scraping.default', 'jina');
    config()->set('scraping.fallbacks', []);
    config()->set('scraping.jina.api_key', null);

    $result = (new ScrapingManager(app()))->extractFor('https://example.com/a');

    expect($result->status)->toBe(ExtractionStatus::Unsupported)
        ->and($result->extractor)->toBe('none')
        ->and($result->error)->toBe('No extractor supports this URL');
});
