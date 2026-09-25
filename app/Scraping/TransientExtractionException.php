<?php

namespace App\Scraping;

use App\Jobs\ExtractContentJob;
use RuntimeException;

/**
 * Thrown by {@see ExtractContentJob} after recording a transient
 * failure (a timeout, a 5xx), so the queue retries the job with backoff.
 */
final class TransientExtractionException extends RuntimeException {}
