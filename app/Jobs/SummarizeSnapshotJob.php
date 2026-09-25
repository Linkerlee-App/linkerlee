<?php

namespace App\Jobs;

use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\SummaryManager;
use App\Models\LinkSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a snapshot's `summary` and `summary_model`, the first step of the
 * enrichment chain.
 *
 * Idempotent: a snapshot already summarized by the model in use is left
 * alone, so only a model change (the `$model` override) re-summarizes it.
 * A provider failure ({@see EnrichmentProviderException}) is left to fail the
 * job, which retries under {@see self::backoff()}; it never touches the link's
 * extraction status or the snapshot's text.
 */
class SummarizeSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * A link force-deleted (and its snapshots with it) before the job runs
     * makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  string|null  $model  Summarize with this model instead of the configured default.
     */
    public function __construct(public LinkSnapshot $snapshot, public ?string $model = null)
    {
        $this->onQueue('enrichment');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * Skipped when the snapshot is no longer its live link's latest.
     */
    public function handle(SummaryManager $summaries): void
    {
        if (! $this->snapshot->isCurrent()) {
            return;
        }

        $provider = $summaries->provider($this->model);

        if ($this->snapshot->summary !== null && $this->snapshot->summary_model === $provider->model()) {
            return;
        }

        $summary = $provider->summarize($this->snapshot->title ?? '', (string) $this->snapshot->content_text);

        $this->snapshot->update([
            'summary' => $summary,
            'summary_model' => $provider->model(),
        ]);
    }
}
