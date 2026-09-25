<?php

namespace App\Scraping;

/**
 * What {@see SnapshotRecorder::record()} did with an extraction result.
 */
enum RecordOutcome
{
    /**
     * The text changed (or is the first), so a new snapshot was stored.
     */
    case Created;

    /**
     * The text hashes the same as the latest snapshot; only the link's
     * extraction bookkeeping was refreshed.
     */
    case Unchanged;

    /**
     * The extraction failed; the status and error were recorded and every
     * existing snapshot was left alone.
     */
    case Failed;
}
