<?php

namespace App\Enrichment\Providers;

use App\Enrichment\Contracts\SummaryProvider;
use App\Jobs\SummarizeSnapshotJob;

/**
 * The default summary driver (`SUMMARY_DRIVER=none`): summaries are off, so
 * no page text ever leaves the server to be summarized.
 *
 * {@see SummarizeSnapshotJob} recognises it and returns before calling it, so
 * a snapshot keeps a null `summary` and chunk 0 is its title alone.
 */
final class NullSummaryProvider implements SummaryProvider
{
    public const MODEL = 'none';

    public function summarize(string $title, string $text): string
    {
        return '';
    }

    public function model(): string
    {
        return self::MODEL;
    }

    /**
     * There is no model to switch to, so a `--model` override changes nothing.
     */
    public function withModel(string $model): static
    {
        return $this;
    }
}
