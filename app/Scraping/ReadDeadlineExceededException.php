<?php

namespace App\Scraping;

use App\Scraping\Drivers\HttpReadabilityExtractor;
use RuntimeException;

/**
 * Thrown from {@see HttpReadabilityExtractor}'s body
 * read loop once the wall-clock deadline passes.
 *
 * `Http::timeout()` bounds each individual read on a streamed response, not
 * the total time spent reading it, so a server that drips a few bytes at a
 * time could otherwise hold a job open indefinitely.
 */
final class ReadDeadlineExceededException extends RuntimeException {}
