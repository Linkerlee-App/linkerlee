<?php

namespace App\Enrichment;

/**
 * A provider failure that retrying cannot fix: a missing API key, or a
 * request the provider rejects outright (bad key, forbidden, malformed
 * request, unknown model).
 *
 * A job fails on it after the first attempt instead of retrying under its
 * backoff, so a misconfigured provider does not fill `failed_jobs` five
 * attempts at a time.
 */
final class NonRetryableProviderException extends EnrichmentProviderException {}
