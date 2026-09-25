<?php

use App\Enrichment\Contracts\SummaryProvider;
use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Enrichment\Providers\FakeSummaryProvider;
use App\Enrichment\Providers\NullSummaryProvider;
use App\Enrichment\SummaryManager;
use App\Jobs\SummarizeSnapshotJob;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\SnapshotRecorder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();

    config()->set('enrichment.summary.driver', 'none');
    config()->set('enrichment.summary.anthropic.api_key', 'would-be-sent');
});

afterEach(function () {
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

test('the none driver resolves to a provider that summarizes nothing', function () {
    $provider = app(SummaryManager::class)->provider();

    expect($provider)->toBeInstanceOf(NullSummaryProvider::class)
        ->and($provider->summarize('Title', 'Text'))->toBe('')
        ->and($provider->model())->toBe('none')
        ->and($provider->withModel('claude-haiku-4-5')->model())->toBe('none');
});

test('under the none driver a new snapshot is chunked and embedded without any summary call', function () {
    $link = Link::factory()->create(['user_id' => User::factory()]);

    app(SnapshotRecorder::class)->record(
        $link,
        ExtractionResult::ok('fake', "First paragraph.\n\nSecond paragraph.", ['title' => 'Private page']),
        $link->link,
    );

    $snapshot = $link->fresh()->latestSnapshot;
    $chunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->orderBy('ordinal')->get();

    Http::assertNothingSent();

    expect(FakeSummaryProvider::$calls)->toBe([])
        ->and($snapshot->summary)->toBeNull()
        ->and($snapshot->summary_model)->toBeNull()
        ->and($chunks->first()->text)->toBe('Private page')
        ->and($chunks->count())->toBeGreaterThanOrEqual(2)
        ->and($chunks->whereNull('embedding')->count())->toBe(0);
});

test('an empty summary from a provider leaves summary and summary_model null', function () {
    config()->set('enrichment.summary.driver', 'fake');
    app()->bind(FakeSummaryProvider::class, fn (): SummaryProvider => new class implements SummaryProvider
    {
        public function summarize(string $title, string $text): string
        {
            return '   ';
        }

        public function model(): string
        {
            return 'blank-model';
        }
    });

    $link = Link::factory()->create(['user_id' => User::factory()]);
    $snapshot = LinkSnapshot::factory()->create(['link_id' => $link->id]);
    $link->forceFill(['latest_snapshot_id' => $snapshot->id])->save();

    SummarizeSnapshotJob::dispatchSync($snapshot);

    expect($snapshot->fresh()->summary)->toBeNull()
        ->and($snapshot->fresh()->summary_model)->toBeNull();
});

test('resummarize under the none driver dispatches nothing and says summaries are disabled', function () {
    Queue::fake();
    Bus::fake();

    $link = Link::factory()->create(['user_id' => User::factory()]);
    $snapshot = LinkSnapshot::factory()->create(['link_id' => $link->id, 'summary' => null]);
    $link->forceFill(['latest_snapshot_id' => $snapshot->id])->save();

    $this->artisan('linkerlee:resummarize')
        ->assertSuccessful()
        ->expectsOutputToContain('Summaries are disabled');

    Queue::assertNothingPushed();
    Bus::assertNothingDispatched();
});
