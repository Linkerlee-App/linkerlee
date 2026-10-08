<?php

namespace App\Events;

use App\Models\LinkSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new version of a link's article text was stored.
 *
 * Fired only after the snapshot row is committed, so a listener can always
 * read it back.
 */
class LinkSnapshotCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public LinkSnapshot $snapshot) {}
}
