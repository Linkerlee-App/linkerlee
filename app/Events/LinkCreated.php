<?php

namespace App\Events;

use App\Models\Link;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user saved a new link.
 *
 * Fired explicitly by each place that creates a link rather than from a model
 * hook, so factories in tests never trigger content extraction.
 */
class LinkCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Link $link) {}
}
