<?php

namespace App\Enrichment\Providers;

use App\Enrichment\Contracts\SummaryProvider;
use App\Enrichment\EnrichmentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Summarizes a link snapshot's title and text with Claude Haiku, called over
 * plain HTTP against the Messages API (no SDK dependency).
 */
final class AnthropicSummaryProvider implements SummaryProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const MAX_TOKENS = 200;

    private readonly string $model;

    public function __construct(?string $model = null)
    {
        $this->model = $model ?? (string) config('enrichment.summary.anthropic.model');
    }

    public function model(): string
    {
        return $this->model;
    }

    public function withModel(string $model): static
    {
        return new self($model);
    }

    public function summarize(string $title, string $text): string
    {
        $apiKey = (string) config('enrichment.summary.anthropic.api_key');

        if ($apiKey === '') {
            throw new EnrichmentProviderException('Cannot summarize: enrichment.summary.anthropic.api_key (ANTHROPIC_API_KEY) is not set.');
        }

        $maxInputChars = (int) config('enrichment.summary.anthropic.max_input_chars');
        $prompt = $this->prompt($title, mb_substr($text, 0, $maxInputChars));

        try {
            $response = Http::timeout((int) config('enrichment.summary.anthropic.timeout', 30))
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                ])
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new EnrichmentProviderException("Connection error while summarizing with Anthropic: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            throw new EnrichmentProviderException("Anthropic summary request failed with HTTP {$response->status()}: {$response->body()}");
        }

        $summary = $response->json('content.0.text');

        if (! is_string($summary) || trim($summary) === '') {
            throw new EnrichmentProviderException('Anthropic summary response is missing its content text.');
        }

        return trim($summary);
    }

    private function prompt(string $title, string $text): string
    {
        return <<<PROMPT
        Summarize in 2–3 sentences.

        Title: {$title}

        {$text}
        PROMPT;
    }
}
