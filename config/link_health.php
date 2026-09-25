<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batch size
    |--------------------------------------------------------------------------
    |
    | How many due links the health check processes per invocation.
    |
    */

    'batch_size' => (int) env('LINK_HEALTH_BATCH_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Check interval bounds, in days
    |--------------------------------------------------------------------------
    |
    | A link's first check is scheduled this many days out. After that, the
    | interval grows on a clean check and shrinks on a failure, bounded by
    | "max_interval_days".
    |
    */

    'initial_interval_days' => (int) env('LINK_HEALTH_INITIAL_INTERVAL_DAYS', 7),

    'max_interval_days' => (int) env('LINK_HEALTH_MAX_INTERVAL_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Error retry
    |--------------------------------------------------------------------------
    |
    | How soon, in days, a link is rechecked after a failed check.
    |
    */

    'error_retry_days' => (int) env('LINK_HEALTH_ERROR_RETRY_DAYS', 1),

    /*
    |--------------------------------------------------------------------------
    | Probe deadline
    |--------------------------------------------------------------------------
    |
    | The most wall-clock seconds one health check's probe may spend across
    | all of its redirect hops, kept well inside the job's 60s timeout.
    | Running past it counts as a failed check.
    |
    */

    'probe_deadline_seconds' => (int) env('LINK_HEALTH_PROBE_DEADLINE_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Failure threshold
    |--------------------------------------------------------------------------
    |
    | Consecutive failed checks before a link is marked Gone.
    |
    */

    'failure_threshold' => (int) env('LINK_HEALTH_FAILURE_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Domain throttle
    |--------------------------------------------------------------------------
    |
    | Minimum seconds between health checks against the same domain.
    |
    */

    'domain_throttle_seconds' => (int) env('LINK_HEALTH_DOMAIN_THROTTLE_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Noise detection
    |--------------------------------------------------------------------------
    |
    | How similar two shingled texts must be to count as "no real change" in
    | the page, and the shingle size (in words) the comparison is built from.
    |
    */

    'noise_similarity' => (float) env('LINK_HEALTH_NOISE_SIMILARITY', 0.9),

    'shingle_size' => (int) env('LINK_HEALTH_SHINGLE_SIZE', 5),

    /*
    |--------------------------------------------------------------------------
    | Soft-404 detection
    |--------------------------------------------------------------------------
    |
    | A page that still answers with 200 but reads like a "not found" page:
    | enough of its words dropped since the last snapshot, together with one
    | of these phrases appearing, marks it Gone instead of Ok.
    |
    */

    'soft_404_word_drop' => (float) env('LINK_HEALTH_SOFT_404_WORD_DROP', 0.8),

    'soft_404_patterns' => [
        'not found',
        'page not found',
        '404',
        'no longer available',
        'does not exist',
        'page unavailable',
    ],

];
