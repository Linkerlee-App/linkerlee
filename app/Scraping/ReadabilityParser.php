<?php

namespace App\Scraping;

use Carbon\CarbonImmutable;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Throwable;

/**
 * Wraps fivefilters/readability.php to turn a page's raw HTML into an
 * article: title, author, published date and body text with HTML stripped
 * but paragraph breaks kept.
 *
 * Readability.js (and this port) always assumes its input is UTF-8, so a
 * page served in another encoding — Latin-1 news archives are common — has
 * to be converted before it reaches the parser, or every accented
 * character comes out corrupted.
 */
final class ReadabilityParser
{
    /**
     * @return array{title: ?string, author: ?string, published_at: ?CarbonImmutable, text: string}|null
     */
    public static function parse(string $html, string $url): ?array
    {
        $html = self::toUtf8($html);

        $readability = new Readability(new Configuration([
            'fixRelativeURLs' => true,
            'originalURL' => $url,
            'articleByline' => true,
        ]));

        try {
            $parsed = $readability->parse($html);
        } catch (ParseException) {
            $parsed = false;
        }

        $content = $parsed ? $readability->getContent() : null;

        if ($content === null || trim(strip_tags($content)) === '') {
            // Readability found no article (a JavaScript app shell, a page
            // that's mostly chrome, ...). Fall back to the raw body instead
            // of failing outright: a driver still got usable HTML back, and
            // whether the resulting text is too thin to keep is a decision
            // for the caller (config('scraping.min_word_count')), not this
            // parser.
            return self::fallbackToBody($html);
        }

        return [
            'title' => $readability->getTitle(),
            'author' => $readability->getAuthor(),
            'published_at' => self::extractPublishedAt($html),
            'text' => self::htmlToText($content),
        ];
    }

    /**
     * @return array{title: ?string, author: ?string, published_at: ?CarbonImmutable, text: string}|null
     */
    private static function fallbackToBody(string $html): ?array
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches) !== 1) {
            return null;
        }

        $bodyHtml = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $matches[1]);
        $text = trim(self::htmlToText($bodyHtml));

        if ($text === '') {
            return null;
        }

        return [
            'title' => self::extractTitleTag($html),
            'author' => null,
            'published_at' => self::extractPublishedAt($html),
            'text' => $text,
        ];
    }

    private static function extractTitleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
    }

    /**
     * Strips tags while turning paragraph and line-break boundaries into
     * newlines, so the result reads like plain text instead of one long
     * run-on line. {@see ExtractionResult::normalize()} does the final
     * whitespace clean-up once this is handed to a driver.
     */
    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|tr|table)\s*>/i', "\n\n", $html);

        $text = strip_tags($html);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Readability.php extracts title, author and excerpt from meta tags and
     * JSON-LD, but not a publish date, so this looks for the common places
     * one is declared.
     */
    private static function extractPublishedAt(string $html): ?CarbonImmutable
    {
        $patterns = [
            '/<meta[^>]+(?:property|name)=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+(?:property|name)=["\']og:published_time["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+name=["\']date["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/<time[^>]+datetime=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                try {
                    return CarbonImmutable::parse($matches[1]);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Detects the page's declared charset from its <meta> tags and, when it
     * is not already UTF-8, converts the raw bytes.
     */
    private static function toUtf8(string $html): string
    {
        $charset = self::declaredCharset($html);

        if ($charset === null || in_array(strtoupper($charset), ['UTF-8', 'UTF8'], true)) {
            return $html;
        }

        $converted = @mb_convert_encoding($html, 'UTF-8', $charset);

        return $converted === false ? $html : $converted;
    }

    private static function declaredCharset(string $html): ?string
    {
        if (preg_match('/<meta[^>]+charset=["\']?\s*([\w-]+)/i', $html, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/<meta[^>]+http-equiv=["\']Content-Type["\'][^>]*content=["\'][^"\']*charset=([\w-]+)/i', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
