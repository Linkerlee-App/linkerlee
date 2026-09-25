<?php

namespace App\Enrichment;

use RuntimeException;

/**
 * Thrown by a {@see Contracts\SummaryProvider} or {@see Contracts\EmbeddingProvider}
 * on a non-2xx response, a connection error, or a malformed response (missing
 * content, or an embeddings count that doesn't match the input).
 *
 * Left uncaught, this fails the queued job that called the provider so it
 * retries under the job's own backoff, rather than storing a summary or an
 * embedding derived from a broken response. The non-retryable cases are the
 * {@see NonRetryableProviderException} subclass.
 */
class EnrichmentProviderException extends RuntimeException {}
