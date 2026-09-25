<?php

namespace App\Health;

use App\Enums\HealthStatus;
use App\Models\LinkSnapshot;
use App\Scraping\ExtractionResult;

/**
 * Turns one HTTP response, or one extraction result, into a
 * {@see HealthStatus} without ever touching the network or the database.
 */
class HealthClassifier
{
    /**
     * Classifies a health check's HTTP response.
     *
     * `$status` is the status of the last response seen: the final page
     * after a redirect walk, or the redirect itself when the walk stopped
     * there. `$permanentRedirect` says whether a hop on the way was a
     * 301/308 (permanent) rather than a 302/307 (temporary); only a
     * permanent redirect that ends on a genuinely different host, and whose
     * final response is not a 4xx/5xx, counts as
     * {@see HealthStatus::Redirected}. A redirect that ends on a missing or
     * failing page is classified by that page. A 304 is a success like a 2xx:
     * the probe only sends validators taken from the last good fetch, so it
     * means the page is still that page. Everything else that isn't 2xx,
     * 304 or 404/410 is treated as {@see HealthStatus::Error}, including
     * temporary redirects and permanent redirects that stay on the same
     * host.
     */
    public function classifyResponse(int $status, ?string $originalHost, ?string $finalHost, bool $permanentRedirect): HealthStatus
    {
        if ($permanentRedirect && $status >= 200 && $status < 400 && $this->hostsDiffer($originalHost, $finalHost)) {
            return HealthStatus::Redirected;
        }

        if (($status >= 200 && $status < 300) || $status === 304) {
            return HealthStatus::Ok;
        }

        if ($status === 404 || $status === 410) {
            return HealthStatus::Gone;
        }

        return HealthStatus::Error;
    }

    /**
     * Whether a 200 response actually reads like a "not found" page: its
     * title or (for a short page) its body matches one of the configured
     * phrases, or its word count collapsed relative to the last snapshot.
     */
    public function isSoft404(ExtractionResult $r, ?LinkSnapshot $latest): bool
    {
        if ($this->matchesPattern($r->title) || ($r->wordCount < 200 && $this->matchesPattern($r->contentText))) {
            return true;
        }

        if ($latest !== null) {
            $drop = (float) config('link_health.soft_404_word_drop');

            if ($r->wordCount < $latest->word_count * (1 - $drop)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitive, word-boundary match against the configured
     * soft-404 phrases, so "404" matches "Error 404" but not "4040".
     */
    private function matchesPattern(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        /** @var list<string> $patterns */
        $patterns = config('link_health.soft_404_patterns');

        $alternation = implode('|', array_map(
            fn (string $pattern): string => preg_quote($pattern, '/'),
            $patterns,
        ));

        return (bool) preg_match('/\b(?:'.$alternation.')\b/iu', $text);
    }

    /**
     * Whether two hosts genuinely differ, after stripping a leading
     * "www." and lowercasing both. A null host never counts as different
     * from anything, since a missing host means there's nothing to
     * compare against.
     */
    public function hostsDiffer(?string $originalHost, ?string $finalHost): bool
    {
        if ($originalHost === null || $finalHost === null) {
            return false;
        }

        return $this->normalizeHost($originalHost) !== $this->normalizeHost($finalHost);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
