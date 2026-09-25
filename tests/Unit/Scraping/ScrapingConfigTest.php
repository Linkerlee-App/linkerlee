<?php

use App\Scraping\Contracts\ContentExtractor;

test('every configured driver class exists and implements ContentExtractor', function () {
    $drivers = config('scraping.drivers');

    expect($drivers)->toBeArray()->not->toBeEmpty();

    foreach ($drivers as $name => $class) {
        expect(class_exists($class))
            ->toBeTrue("Driver [{$name}] points at [{$class}], which does not exist.");

        expect(in_array(ContentExtractor::class, class_implements($class), true))
            ->toBeTrue("Driver [{$name}]'s class [{$class}] does not implement ".ContentExtractor::class);
    }
});

test('the test suite forces the fake driver with no fallbacks, so no test ever touches the network', function () {
    expect(config('scraping.default'))->toBe('fake')
        ->and(config('scraping.fallbacks'))->toBe([]);
});

test('scraping thresholds are configured', function () {
    expect(config('scraping.timeout'))->toBeInt()
        ->and(config('scraping.max_bytes'))->toBeInt()
        ->and(config('scraping.min_word_count'))->toBeInt()
        ->and(config('scraping.user_agent'))->toBeString()->not->toBeEmpty();
});
