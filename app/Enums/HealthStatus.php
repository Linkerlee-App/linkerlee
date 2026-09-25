<?php

namespace App\Enums;

/**
 * The result of the most recent health check against a link's URL.
 *
 * Null on the model (not a case of this enum) means the link has never been
 * checked yet.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Redirected = 'redirected';
    case Suspect = 'suspect';
    case Gone = 'gone';
    case Error = 'error';
}
