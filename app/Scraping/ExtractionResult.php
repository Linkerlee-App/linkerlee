<?php

namespace App\Scraping;

use App\Enums\ExtractionStatus;
use Carbon\CarbonImmutable;

/**
 * The outcome of one driver's attempt to turn a URL into article text.
 *
 * Immutable and dumb on purpose: a driver builds one of these instead of
 * throwing, so a caller can inspect {@see self::$status} and {@see
 * self::$transient} to decide whether to retry, fall back to another
 * driver, or record the failure, without a try/catch anywhere.
 */
final readonly class ExtractionResult
{
    public function __construct(
        public ExtractionStatus $status,
        public string $extractor,
        public ?int $httpStatus = null,
        public ?string $finalUrl = null,
        public ?string $etag = null,
        public ?string $lastModified = null,
        public ?string $title = null,
        public ?string $author = null,
        public ?CarbonImmutable $publishedAt = null,
        public ?string $contentText = null,
        public ?string $contentHash = null,
        public int $wordCount = 0,
        public ?string $error = null,
        public bool $transient = false,
    ) {}

    /**
     * @param  array{httpStatus?: ?int, finalUrl?: ?string, etag?: ?string, lastModified?: ?string, title?: ?string, author?: ?string, publishedAt?: ?CarbonImmutable}  $attrs
     */
    public static function ok(string $extractor, string $text, array $attrs = []): self
    {
        $normalized = self::normalize($text);

        return new self(
            status: ExtractionStatus::Ok,
            extractor: $extractor,
            httpStatus: $attrs['httpStatus'] ?? null,
            finalUrl: $attrs['finalUrl'] ?? null,
            etag: $attrs['etag'] ?? null,
            lastModified: $attrs['lastModified'] ?? null,
            title: $attrs['title'] ?? null,
            author: $attrs['author'] ?? null,
            publishedAt: $attrs['publishedAt'] ?? null,
            contentText: $normalized,
            contentHash: hash('sha256', $normalized),
            wordCount: self::countWords($normalized),
        );
    }

    /**
     * @param  array{httpStatus?: ?int, finalUrl?: ?string, etag?: ?string, lastModified?: ?string, transient?: bool}  $attrs
     */
    public static function failure(ExtractionStatus $status, string $extractor, string $error, array $attrs = []): self
    {
        return new self(
            status: $status,
            extractor: $extractor,
            httpStatus: $attrs['httpStatus'] ?? null,
            finalUrl: $attrs['finalUrl'] ?? null,
            etag: $attrs['etag'] ?? null,
            lastModified: $attrs['lastModified'] ?? null,
            error: $error,
            transient: $attrs['transient'] ?? false,
        );
    }

    /**
     * Trims each line, collapses runs of horizontal whitespace within a
     * line to a single space, collapses three or more consecutive
     * newlines down to two (one blank line), and trims the whole text.
     */
    public static function normalize(string $text): string
    {
        $lines = array_map(
            fn (string $line): string => trim(preg_replace('/[^\S\r\n]+/u', ' ', $line)),
            explode("\n", $text),
        );

        $text = preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines));

        return trim($text);
    }

    public function isOk(): bool
    {
        return $this->status === ExtractionStatus::Ok;
    }

    /**
     * str_word_count() is ASCII-only, so it silently drops accented and
     * non-Latin words; this counts on whitespace boundaries instead.
     */
    private static function countWords(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }
}
