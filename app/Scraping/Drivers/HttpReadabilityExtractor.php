<?php

namespace App\Scraping\Drivers;

use App\Enums\ExtractionStatus;
use App\Scraping\BlockedRedirectException;
use App\Scraping\Contracts\ContentExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\ReadabilityParser;
use App\Scraping\ReadDeadlineExceededException;
use App\Scraping\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * The default extraction driver: a plain HTTP GET followed by
 * {@see ReadabilityParser}. Works for any server-rendered page; a
 * JavaScript-only page falls through to {@see BrowsershotExtractor} via
 * config('scraping.fallbacks') because it will come back with a very low
 * word count, not because this driver fails.
 */
final class HttpReadabilityExtractor implements ContentExtractor
{
    private const NAME = 'http_readability';

    private const ALLOWED_CONTENT_TYPES = ['text/html', 'application/xhtml+xml'];

    private const MAX_REDIRECTS = 5;

    private const READ_CHUNK_SIZE = 8192;

    public function supports(string $url): bool
    {
        return true;
    }

    public function extract(string $url): ExtractionResult
    {
        if (($error = UrlGuard::check($url)) !== null) {
            return ExtractionResult::failure(ExtractionStatus::Blocked, self::NAME, $error);
        }

        try {
            $response = Http::timeout((int) config('scraping.timeout'))
                ->withUserAgent((string) config('scraping.user_agent'))
                ->withOptions([
                    'stream' => true,
                    'allow_redirects' => [
                        'max' => self::MAX_REDIRECTS,
                        // The URL a link points to can redirect anywhere,
                        // including at a private or link-local address, so
                        // every hop needs the same SSRF check the original
                        // URL got. Throwing here stops Guzzle from ever
                        // sending a request to a blocked target.
                        'on_redirect' => function ($request, $guzzleResponse, UriInterface $uri): void {
                            if (($error = UrlGuard::check((string) $uri)) !== null) {
                                throw new BlockedRedirectException($error);
                            }
                        },
                    ],
                ])
                ->get($url);
        } catch (BlockedRedirectException $exception) {
            return ExtractionResult::failure(ExtractionStatus::Blocked, self::NAME, $exception->getMessage());
        } catch (ConnectionException $exception) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage(), [
                'transient' => true,
            ]);
        }

        $status = $response->status();

        $attrs = [
            'httpStatus' => $status,
            'finalUrl' => (string) ($response->effectiveUri() ?? $url),
            'etag' => $response->header('ETag') ?: null,
            'lastModified' => $response->header('Last-Modified') ?: null,
        ];

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

        if ($status >= 400) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, "HTTP {$status}", $attrs);
        }

        $contentType = $this->baseContentType($response->header('Content-Type'));

        if (! in_array($contentType, self::ALLOWED_CONTENT_TYPES, true)) {
            return ExtractionResult::failure(ExtractionStatus::Unsupported, self::NAME, "Unsupported content type: {$contentType}", $attrs);
        }

        try {
            $body = $this->readBody($response, (int) config('scraping.max_bytes'), (float) config('scraping.timeout'));
        } catch (ReadDeadlineExceededException $exception) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage(), [
                ...$attrs,
                'transient' => true,
            ]);
        }

        if ($body === null) {
            return ExtractionResult::failure(ExtractionStatus::Unsupported, self::NAME, 'Response body exceeded the configured max_bytes', $attrs);
        }

        try {
            $parsed = ReadabilityParser::parse($body, $url);
        } catch (Throwable $exception) {
            // ReadabilityParser wraps a third-party library fed the page's
            // own (attacker-controlled) HTML; a driver must never throw,
            // whatever that library does with a malformed page.
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, $exception->getMessage(), $attrs);
        }

        if ($parsed === null) {
            return ExtractionResult::failure(ExtractionStatus::Failed, self::NAME, 'Unable to extract article content', $attrs);
        }

        return ExtractionResult::ok(self::NAME, $parsed['text'], [
            ...$attrs,
            'title' => $parsed['title'],
            'author' => $parsed['author'],
            'publishedAt' => $parsed['published_at'],
        ]);
    }

    private function baseContentType(?string $header): string
    {
        return strtolower(trim(explode(';', $header ?? '')[0]));
    }

    /**
     * Reads at most `max_bytes` from the response's stream body and stops,
     * so a 200 MB target never has to be downloaded in full. Returns null
     * when there is more data than that, so the caller can treat it as
     * unsupported rather than silently truncating an article mid-word.
     *
     * `Http::timeout()` only bounds each individual read on a streamed
     * response, not the total time spent reading it, so a server that
     * drips a byte at a time could otherwise hold the job open well past
     * the configured timeout. This adds its own wall-clock deadline on top.
     *
     * @throws ReadDeadlineExceededException
     */
    private function readBody(Response $response, int $maxBytes, float $timeoutSeconds): ?string
    {
        $stream = $response->toPsrResponse()->getBody();
        $deadline = microtime(true) + $timeoutSeconds;
        $buffer = '';

        try {
            while (! $stream->eof() && strlen($buffer) <= $maxBytes) {
                $chunk = $stream->read(self::READ_CHUNK_SIZE);

                if ($chunk === '') {
                    break;
                }

                $buffer .= $chunk;

                if (microtime(true) > $deadline) {
                    throw new ReadDeadlineExceededException('The read deadline was exceeded before the response body finished.');
                }
            }
        } finally {
            $stream->close();
        }

        return strlen($buffer) > $maxBytes ? null : $buffer;
    }
}
