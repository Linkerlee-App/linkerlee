<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('link health thresholds match the agreed defaults', function () {
    expect(config('link_health.batch_size'))->toBe(500)
        ->and(config('link_health.initial_interval_days'))->toBe(7)
        ->and(config('link_health.max_interval_days'))->toBe(90)
        ->and(config('link_health.error_retry_days'))->toBe(1)
        ->and(config('link_health.failure_threshold'))->toBe(3)
        ->and(config('link_health.domain_throttle_seconds'))->toBe(10)
        ->and(config('link_health.noise_similarity'))->toBe(0.9)
        ->and(config('link_health.shingle_size'))->toBe(5)
        ->and(config('link_health.soft_404_word_drop'))->toBe(0.8)
        ->and(config('link_health.soft_404_patterns'))->toBe([
            'not found',
            'page not found',
            '404',
            'no longer available',
            'does not exist',
            'page unavailable',
        ]);
});
