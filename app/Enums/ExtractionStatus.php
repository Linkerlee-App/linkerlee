<?php

namespace App\Enums;

/**
 * The outcome of one content-extraction attempt against a link's URL.
 *
 * A driver never throws: whatever happens while fetching or parsing a page
 * is reported through this status instead, so callers can decide whether to
 * retry, fall back to another driver, or give up.
 */
enum ExtractionStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case Unsupported = 'unsupported';
}
