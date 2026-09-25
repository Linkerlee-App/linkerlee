<?php

namespace App\Health;

use App\Enums\HealthStatus;

/**
 * What one link-health check found, feeding {@see CheckSchedule::next()}.
 *
 * This is distinct from {@see HealthStatus}, which is the status
 * persisted on the link: several outcomes here can produce the same status
 * (`Failure` and `Suspect` both leave `health_status` as `error`/`suspect`
 * without changing the schedule the same way), and this enum only ever
 * lives for the duration of one check.
 */
enum CheckOutcome
{
    case Unchanged;
    case Changed;
    case Failure;
    case Gone;
    case Suspect;
}
