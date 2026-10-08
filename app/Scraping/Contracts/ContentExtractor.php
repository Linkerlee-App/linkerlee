<?php

namespace App\Scraping\Contracts;

use App\Scraping\ExtractionResult;

/**
 * One way of turning a URL into article text.
 *
 * A driver never throws for anything the network or the target page can do
 * to it (timeouts, non-HTML content, blocked hosts, oversized bodies); it
 * reports the outcome through {@see ExtractionResult} instead, so callers
 * can retry, fall back to another driver, or give up without a try/catch.
 */
interface ContentExtractor
{
    public function extract(string $url): ExtractionResult;

    /**
     * Whether this driver is able to handle the given URL at all, given how
     * it is currently configured (e.g. Browsershot needs a local Chrome).
     */
    public function supports(string $url): bool;
}
