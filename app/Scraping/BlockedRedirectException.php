<?php

namespace App\Scraping;

use App\Scraping\Drivers\HttpReadabilityExtractor;
use RuntimeException;

/**
 * Thrown from inside Guzzle's `on_redirect` callback in
 * {@see HttpReadabilityExtractor} to abort following
 * a redirect {@see UrlGuard} rejects.
 *
 * A saved link's page can redirect anywhere, including at a private or
 * link-local address, so every hop needs the same SSRF check as the
 * original URL — not just the one the user saved. Throwing here stops
 * Guzzle before it sends a request to the blocked target.
 */
final class BlockedRedirectException extends RuntimeException {}
