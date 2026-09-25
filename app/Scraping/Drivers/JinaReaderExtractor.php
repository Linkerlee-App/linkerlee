<?php

namespace App\Scraping\Drivers;

use App\Enums\ExtractionStatus;
use App\Scraping\Contracts\ContentExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\UrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jina AI's "Reader" API (https://r.jina.ai/): a hosted service that
 * fetches and cleans a page for us. Useful as a fallback for pages that
 * defeat both the plain HTTP driver and a local headless Chrome, but it
 * requires an API key, so {@see self::supports()} says no without one.
 */
final class JinaReaderExtractor implements ContentExtractor
{
    private const NAME = 'jina';

    private const ENDPOINT = 'https://r.jina.ai/';

    public function supports(string $url): bool
    {
        return filled(config('scraping.jina.api_key'));
    }

    public function extract(string $url): ExtractionResult
    {
        if (($error = UrlGuard::check($url)) !== null) {
            return ExtractionResult::failure(ExtractionStatus::Blocked, self::NAME, $error);
        }

        try {
            $response = Http::timeout((int) config('scraping.timeout'))
                ->withUserAgent((string) config('scraping.user_agent'))
                ->withToken((string) config('scraping.jina.api_key'))
                ->acceptJson()
                ->get(self::ENDPOINT.$url);
        } catch (ConnectionException $exception) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage(), [
                'transient' => true,
            ]);
        }

        $status = $response->status();
        $attrs = ['httpStatus' => $status];

        if (in_array($status, [401, 403, 429], true)) {
            return ExtractionResult::failure(ExtractionStatus::Blocked, self::NAME, "Blocked with HTTP {$status}", [
                ...$attrs,
                'transient' => $status === 429,
            ]);
        }

        if ($status >= 500) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, "HTTP {$status}", [
                ...$attrs,
                'transient' => true,
            ]);
        }

        if (! $response->successful()) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, "HTTP {$status}", $attrs);
        }

        $data = (array) $response->json('data', []);
        $text = $data['content'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, 'Empty response from Jina Reader', $attrs);
        }

        return ExtractionResult::ok(self::NAME, $text, [
            ...$attrs,
            'finalUrl' => $url,
            'title' => $data['title'] ?? null,
            'publishedAt' => $this->parsePublishedTime($data['publishedTime'] ?? null),
        ]);
    }

    private function parsePublishedTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
