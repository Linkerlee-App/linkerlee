<?php

use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\Providers\AnthropicSummaryProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('enrichment.summary.anthropic.api_key', 'test-key');
    config()->set('enrichment.summary.anthropic.model', 'claude-haiku-4-5');
    config()->set('enrichment.summary.anthropic.max_input_chars', 24000);
    config()->set('enrichment.summary.anthropic.timeout', 30);
});

test('sends the messages api request shape', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'A short summary.'],
            ],
        ], 200),
    ]);

    $summary = (new AnthropicSummaryProvider)->summarize('Rooftop Beekeeping', 'Bees like rooftops.');

    expect($summary)->toBe('A short summary.');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $body['model'] === 'claude-haiku-4-5'
            && $body['max_tokens'] === 200
            && $body['messages'][0]['role'] === 'user'
            && str_contains($body['messages'][0]['content'], 'Rooftop Beekeeping')
            && str_contains($body['messages'][0]['content'], 'Bees like rooftops.');
    });
});

test('truncates the text to max_input_chars before sending it', function () {
    config()->set('enrichment.summary.anthropic.max_input_chars', 10);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Summary.']],
        ], 200),
    ]);

    (new AnthropicSummaryProvider)->summarize('Title', str_repeat('word ', 100));

    Http::assertSent(function (Request $request) {
        $content = $request->data()['messages'][0]['content'];

        // Only the first 10 characters of the (repeated "word ") body may appear.
        return ! str_contains($content, str_repeat('word ', 100));
    });
});

test('a non-2xx response throws', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn () => (new AnthropicSummaryProvider)->summarize('Title', 'Text'))
        ->toThrow(EnrichmentProviderException::class);
});

test('a response missing content text throws', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => []], 200),
    ]);

    expect(fn () => (new AnthropicSummaryProvider)->summarize('Title', 'Text'))
        ->toThrow(EnrichmentProviderException::class);
});

test('an empty api key throws immediately without any request', function () {
    config()->set('enrichment.summary.anthropic.api_key', '');

    expect(fn () => (new AnthropicSummaryProvider)->summarize('Title', 'Text'))
        ->toThrow(EnrichmentProviderException::class);

    Http::assertNothingSent();
});

test('model and withModel report the effective model', function () {
    $provider = new AnthropicSummaryProvider;

    expect($provider->model())->toBe('claude-haiku-4-5');

    $overridden = $provider->withModel('claude-haiku-4-5-custom');

    expect($overridden->model())->toBe('claude-haiku-4-5-custom')
        ->and($overridden)->not->toBe($provider);
});
