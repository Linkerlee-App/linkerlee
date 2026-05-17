<?php

namespace App\Support;

class InboundEmailParser
{
    private const URL_PATTERN = '/\bhttps?:\/\/[^\s<>"\'`)]+/i';

    public static function extractUrl(?string $body, ?string $subject): ?string
    {
        foreach ([$body, $subject] as $source) {
            if ($source === null || trim($source) === '') {
                continue;
            }

            if (preg_match(self::URL_PATTERN, $source, $matches) === 1) {
                return rtrim($matches[0], '.,;:!?)\'"');
            }
        }

        return null;
    }

    public static function extractTitle(?string $subject, string $url): ?string
    {
        if ($subject === null) {
            return null;
        }

        $cleaned = trim(preg_replace('/^\s*(?:fwd?|re|fw):\s*/i', '', $subject));

        if ($cleaned === '' || $cleaned === $url) {
            return null;
        }

        return $cleaned;
    }
}
