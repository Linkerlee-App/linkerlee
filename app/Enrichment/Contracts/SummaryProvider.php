<?php

namespace App\Enrichment\Contracts;

use App\Enrichment\SummaryManager;

/**
 * One way of turning a link snapshot's title and text into a short summary.
 *
 * A concrete provider also implements `withModel(string $model): static`,
 * returning a clone configured to use that model instead of the configured
 * default. It is not part of this contract because only
 * {@see SummaryManager::provider()} needs it.
 */
interface SummaryProvider
{
    public function summarize(string $title, string $text): string;

    /**
     * The model this instance summarizes with.
     */
    public function model(): string;
}
