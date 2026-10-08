<?php

namespace App\Scraping;

use App\Scraping\Drivers\HttpReadabilityExtractor;
use RuntimeException;

/**
 * Thrown from {@see HttpReadabilityExtractor}'s body read loop when a read
 * returns nothing before the stream reached its end.
 *
 * A per-read timeout surfaces as an empty read rather than an error, so
 * without this a stalled connection would hand a truncated page to the
 * parser as if it were complete.
 */
final class ReadStalledException extends RuntimeException {}
