<?php

use App\Enrichment\Contracts\SummaryProvider;
use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\NonRetryableProviderException;
use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Enrichment\Providers\FakeSummaryProvider;
use App\Enums\ExtractionStatus;
use App\Events\LinkSnapshotCreated;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use App\Listeners\EnrichSnapshot;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\SnapshotRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();

    config()->set('enrichment.chunking.size_tokens', 50);
    config()->set('enrichment.chunking.overlap_tokens', 10);
    config()->set('enrichment.chunking.chars_per_token', 4);

    $this->link = Link::factory()->create(['user_id' => User::factory()]);
});

afterEach(function () {
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

/**
 * Records new article text for the link through the real recorder, which
 * fires LinkSnapshotCreated and so (on the sync test queue) runs the chain.
 */
function recordText(Link $link, string $text, string $title = 'Article title'): LinkSnapshot
{
    app(SnapshotRecorder::class)->record($link, ExtractionResult::ok('fake', $text, ['title' => $title]), $link->link);

    return $link->fresh()->latestSnapshot;
}

/**
 * Four paragraphs of ~150 chars each: with 50-token (200-char) chunks, each
 * lands in its own chunk.
 */
function longText(string $label = 'p'): string
{
    return implode("\n\n", array_map(
        fn (int $i): string => trim(str_repeat("{$label}{$i} word ", 20)),
        range(1, 4),
    ));
}

/**
 * A factory snapshot that is its link's latest, without firing the event.
 */
function latestSnapshotFor(Link $link, array $attributes = []): LinkSnapshot
{
    $snapshot = LinkSnapshot::factory()->create(['link_id' => $link->id, ...$attributes]);
    $link->forceFill(['latest_snapshot_id' => $snapshot->id])->save();

    return $snapshot;
}

test('the chain turns a new snapshot into a summary and embedded chunks', function () {
    $snapshot = recordText($this->link, longText(), 'Great article');

    expect($snapshot->summary)->toBe('Summary of Great article')
        ->and($snapshot->summary_model)->toBe('fake-summary');

    $chunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->orderBy('ordinal')->get();

    expect($chunks->count())->toBeGreaterThan(2)
        ->and($chunks->pluck('ordinal')->all())->toBe(range(0, $chunks->count() - 1))
        ->and($chunks->first()->text)->toBe("Great article\n\nSummary of Great article")
        ->and($chunks->pluck('link_id')->unique()->all())->toBe([$this->link->id]);

    foreach ($chunks as $chunk) {
        expect($chunk->embedding)->toHaveCount(768)
            ->and($chunk->embedding_model)->toBe('fake-embedding')
            ->and($chunk->token_count)->toBe((int) ceil(mb_strlen($chunk->text) / 4));
    }

    expect(FakeSummaryProvider::$calls)->toHaveCount(1);
});

test('each LinkSnapshotCreated event dispatches a summary job and a separate chunk-then-embed chain', function () {
    Bus::fake();

    $snapshot = latestSnapshotFor($this->link);

    LinkSnapshotCreated::dispatch($snapshot);

    expect(Event::getRawListeners()[LinkSnapshotCreated::class])->toBe([EnrichSnapshot::class]);

    Bus::assertDispatchedTimes(SummarizeSnapshotJob::class, 1);
    Bus::assertDispatched(SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->queue === 'enrichment'
        && $job->snapshot->is($snapshot)
        && $job->model === null
        && $job->chained === []);
    Bus::assertChained([
        ChunkSnapshotJob::class,
        EmbedChunksJob::class,
    ]);
    Bus::assertDispatched(ChunkSnapshotJob::class, fn (ChunkSnapshotJob $job): bool => $job->queue === 'enrichment'
        && $job->snapshot->is($snapshot));
});

test('the jobs share their queue and retry settings', function (object $job) {
    expect($job->queue)->toBe('enrichment')
        ->and($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([60, 300, 900, 3600])
        ->and($job->deleteWhenMissingModels)->toBeTrue();
})->with([
    'summarize' => fn () => new SummarizeSnapshotJob(LinkSnapshot::factory()->create()),
    'chunk' => fn () => new ChunkSnapshotJob(LinkSnapshot::factory()->create()),
    'embed' => fn () => new EmbedChunksJob(LinkSnapshot::factory()->create()),
]);

test('a new snapshot deletes the old snapshot chunks but keeps its content_text', function () {
    $old = recordText($this->link, longText('old'));

    expect(ContentChunk::query()->where('link_snapshot_id', $old->id)->count())->toBeGreaterThan(0);

    $new = recordText($this->link, longText('new'));

    expect($new->id)->not->toBe($old->id)
        ->and(ContentChunk::query()->where('link_snapshot_id', $old->id)->count())->toBe(0)
        ->and(ContentChunk::query()->where('link_snapshot_id', $new->id)->count())->toBeGreaterThan(0)
        ->and($old->fresh()->content_text)->toBe(longText('old'));
});

/**
 * Binds the fake summary driver to a provider that always throws the given
 * exception.
 */
function bindThrowingSummaryProvider(Throwable $exception): void
{
    app()->bind(FakeSummaryProvider::class, fn (): SummaryProvider => new class($exception) implements SummaryProvider
    {
        public function __construct(private readonly Throwable $exception) {}

        public function summarize(string $title, string $text): string
        {
            throw $this->exception;
        }

        public function model(): string
        {
            return 'fake-summary';
        }
    });
}

test('a throwing summary provider still leaves the snapshot chunked and embedded, and releases the summary job for retry', function () {
    config()->set('queue.default', 'database');
    bindThrowingSummaryProvider(new EnrichmentProviderException('Anthropic is down'));

    $snapshot = recordText($this->link, longText());

    expect(DB::table('jobs')->where('queue', 'enrichment')->count())->toBe(2);

    $startedAt = now()->getTimestamp();

    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'enrichment', '--stop-when-empty' => true]);

    $job = DB::table('jobs')->where('queue', 'enrichment')->sole();
    $fresh = $snapshot->fresh();
    $chunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->get();

    expect($job->payload)->toContain('SummarizeSnapshotJob')
        ->and($job->attempts)->toBe(1)
        ->and($job->available_at)->toBeGreaterThanOrEqual($startedAt + 60)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($this->link->fresh()->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and($fresh->content_text)->toBe(longText())
        ->and($fresh->summary)->toBeNull()
        ->and($chunks->count())->toBeGreaterThan(1)
        ->and($chunks->whereNull('embedding')->count())->toBe(0)
        ->and($chunks->firstWhere('ordinal', 0)->text)->toBe('Article title');
});

test('a non-retryable provider error fails the summary job after one attempt', function () {
    config()->set('queue.default', 'database');
    config()->set('enrichment.summary.driver', 'anthropic');
    config()->set('enrichment.summary.anthropic.api_key', '');

    $snapshot = latestSnapshotFor($this->link);

    SummarizeSnapshotJob::dispatch($snapshot);

    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'enrichment', '--once' => true]);

    Http::assertNothingSent();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->value('exception'))->toContain(NonRetryableProviderException::class)
        ->and($snapshot->fresh()->summary)->toBeNull();
});

test('a later summary rewrites chunk 0 as title plus summary and re-embeds only chunk 0', function () {
    $snapshot = latestSnapshotFor($this->link, ['title' => 'Late title', 'summary' => null, 'summary_model' => null, 'content_text' => longText()]);

    ChunkSnapshotJob::dispatchSync($snapshot);
    EmbedChunksJob::dispatchSync($snapshot);

    $bodyBefore = ContentChunk::query()->where('ordinal', '>', 0)->orderBy('ordinal')->get(['id', 'text', 'embedding'])->toArray();

    expect(ContentChunk::query()->where('ordinal', 0)->sole()->text)->toBe('Late title');

    FakeEmbeddingProvider::reset();

    SummarizeSnapshotJob::dispatchSync($snapshot);

    $head = ContentChunk::query()->where('ordinal', 0)->sole();
    $expected = (new FakeEmbeddingProvider)->embed(["Late title\n\nSummary of Late title"])[0];

    expect($head->text)->toBe("Late title\n\nSummary of Late title")
        ->and($head->token_count)->toBe((int) ceil(mb_strlen($head->text) / 4))
        ->and($head->embedding)->toEqualWithDelta($expected, 0.0001)
        ->and($head->embedding_model)->toBe('fake-embedding')
        ->and(FakeEmbeddingProvider::$calls[0])->toBe(["Late title\n\nSummary of Late title"])
        ->and(ContentChunk::query()->where('ordinal', '>', 0)->orderBy('ordinal')->get(['id', 'text', 'embedding'])->toArray())->toBe($bodyBefore);
});

test('a later summary for a snapshot with no title inserts a new chunk 0 and keeps the body chunks and their embeddings', function () {
    $snapshot = latestSnapshotFor($this->link, ['title' => null, 'summary' => null, 'summary_model' => null, 'content_text' => longText()]);

    ChunkSnapshotJob::dispatchSync($snapshot);
    EmbedChunksJob::dispatchSync($snapshot);

    $bodyBefore = ContentChunk::query()->orderBy('ordinal')->get(['id', 'text', 'embedding'])->toArray();

    FakeEmbeddingProvider::reset();

    SummarizeSnapshotJob::dispatchSync($snapshot);

    $chunks = ContentChunk::query()->orderBy('ordinal')->get();

    expect($chunks->pluck('ordinal')->all())->toBe(range(0, count($bodyBefore)))
        ->and($chunks->first()->text)->toBe('Summary of')
        ->and($chunks->first()->embedding)->toHaveCount(768)
        ->and(FakeEmbeddingProvider::$calls)->toBe([['Summary of']])
        ->and($chunks->slice(1)->values()->map(fn (ContentChunk $chunk): array => ['id' => $chunk->id, 'text' => $chunk->text, 'embedding' => $chunk->embedding])->all())->toBe($bodyBefore);
});

test('a superseded snapshot makes every job in the chain a no-op', function () {
    $stale = latestSnapshotFor($this->link);
    latestSnapshotFor($this->link);

    SummarizeSnapshotJob::dispatchSync($stale);
    ChunkSnapshotJob::dispatchSync($stale);

    ContentChunk::factory()->create(['link_snapshot_id' => $stale->id, 'link_id' => $this->link->id]);

    EmbedChunksJob::dispatchSync($stale);

    expect($stale->fresh()->summary)->toBeNull()
        ->and(ContentChunk::query()->where('link_snapshot_id', $stale->id)->sole()->embedding)->toBeNull()
        ->and(FakeSummaryProvider::$calls)->toBe([])
        ->and(FakeEmbeddingProvider::$calls)->toBe([]);
});

test('a trashed link makes every job in the chain a no-op', function () {
    $snapshot = latestSnapshotFor($this->link);
    $this->link->delete();

    SummarizeSnapshotJob::dispatchSync($snapshot);
    ChunkSnapshotJob::dispatchSync($snapshot);
    EmbedChunksJob::dispatchSync($snapshot);

    expect($snapshot->fresh()->summary)->toBeNull()
        ->and(ContentChunk::query()->count())->toBe(0)
        ->and(FakeSummaryProvider::$calls)->toBe([]);
});

test('a job whose link was force-deleted before it ran vanishes quietly', function () {
    config()->set('queue.default', 'database');

    recordText($this->link, longText());
    $this->link->forceDelete();

    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'enrichment', '--stop-when-empty' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(FakeSummaryProvider::$calls)->toBe([]);
});

test('re-running the chain produces no duplicate chunks and no repeat provider calls', function () {
    $snapshot = recordText($this->link, longText());
    $chunkCount = ContentChunk::query()->count();

    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();

    Bus::chain([
        new SummarizeSnapshotJob($snapshot),
        new ChunkSnapshotJob($snapshot),
        new EmbedChunksJob($snapshot),
    ])->onQueue('enrichment')->dispatch();

    expect(ContentChunk::query()->count())->toBe($chunkCount)
        ->and(ContentChunk::query()->pluck('ordinal')->sort()->values()->all())->toBe(range(0, $chunkCount - 1))
        ->and(ContentChunk::query()->whereNull('embedding')->count())->toBe(0)
        ->and(FakeSummaryProvider::$calls)->toBe([]);
});

test('summarize with a model override re-summarizes a snapshot summarized by another model', function () {
    $snapshot = latestSnapshotFor($this->link, ['summary' => 'Old', 'summary_model' => 'fake-summary']);

    SummarizeSnapshotJob::dispatchSync($snapshot);

    expect(FakeSummaryProvider::$calls)->toBe([]);

    SummarizeSnapshotJob::dispatchSync($snapshot, 'other-model');

    $fresh = $snapshot->fresh();

    expect(FakeSummaryProvider::$calls)->toHaveCount(1)
        ->and($fresh->summary)->toBe("Summary of {$snapshot->title}")
        ->and($fresh->summary_model)->toBe('other-model');
});

test('chunk 0 is just the title when the snapshot has no summary', function () {
    $snapshot = latestSnapshotFor($this->link, ['title' => 'Only title', 'content_text' => 'Body.']);

    ChunkSnapshotJob::dispatchSync($snapshot);

    expect(ContentChunk::query()->orderBy('ordinal')->pluck('text')->all())->toBe(['Only title', 'Body.'])
        ->and(ContentChunk::query()->whereNotNull('embedding')->count())->toBe(0);
});

test('no empty chunk 0 is stored when there is neither a title nor a summary', function () {
    $snapshot = latestSnapshotFor($this->link, ['title' => null, 'content_text' => 'Body.']);

    ChunkSnapshotJob::dispatchSync($snapshot);

    expect(ContentChunk::query()->get(['ordinal', 'text'])->toArray())->toBe([['ordinal' => 0, 'text' => 'Body.']]);
});

test('embed only embeds chunks that are missing a vector or were embedded by another model', function () {
    $snapshot = latestSnapshotFor($this->link);
    $vector = array_fill(0, 768, 0.5);

    $current = ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'ordinal' => 0, 'text' => 'current', 'embedding' => $vector, 'embedding_model' => 'fake-embedding']);
    $stale = ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'ordinal' => 1, 'text' => 'stale', 'embedding' => $vector, 'embedding_model' => 'old-model']);
    $missing = ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'ordinal' => 2, 'text' => 'missing']);

    EmbedChunksJob::dispatchSync($snapshot);

    expect(FakeEmbeddingProvider::$calls)->toBe([['stale', 'missing']])
        ->and($current->fresh()->embedding)->toEqualWithDelta($vector, 0.0001)
        ->and($stale->fresh()->embedding_model)->toBe('fake-embedding')
        ->and($missing->fresh()->embedding)->toHaveCount(768);
});

test('embed with a model override re-embeds every chunk with that model', function () {
    $snapshot = recordText($this->link, longText());
    FakeEmbeddingProvider::reset();

    EmbedChunksJob::dispatchSync($snapshot, 'other-embedding');

    expect(FakeEmbeddingProvider::$calls)->toHaveCount(1)
        ->and(ContentChunk::query()->where('embedding_model', '!=', 'other-embedding')->count())->toBe(0);
});

test('embed works in batches of batch_size and prepends the driver document prefix', function () {
    config()->set('enrichment.embedding.batch_size', 2);
    config()->set('enrichment.embedding.fake.document_prefix', 'search_document: ');

    $snapshot = latestSnapshotFor($this->link);

    foreach (['one', 'two', 'three'] as $ordinal => $text) {
        ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'ordinal' => $ordinal, 'text' => $text]);
    }

    EmbedChunksJob::dispatchSync($snapshot);

    $expected = (new FakeEmbeddingProvider)->embed(['search_document: one'])[0];

    expect(FakeEmbeddingProvider::$calls)->toBe([
        ['search_document: one', 'search_document: two'],
        ['search_document: three'],
        ['search_document: one'],
    ])
        ->and(ContentChunk::query()->where('text', 'one')->sole()->embedding)->toEqualWithDelta($expected, 0.0001)
        ->and(ContentChunk::query()->where('text', 'one')->sole()->text)->toBe('one');
});

test('embed skips the document prefix when a model override replaces the driver default model', function () {
    config()->set('enrichment.embedding.fake.document_prefix', 'search_document: ');

    $snapshot = latestSnapshotFor($this->link);
    ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'link_id' => $this->link->id, 'ordinal' => 0, 'text' => 'plain']);

    EmbedChunksJob::dispatchSync($snapshot, 'other-embedding');

    expect(FakeEmbeddingProvider::$calls)->toBe([['plain']]);
});

test('embed handles one batch per run and re-dispatches itself while chunks remain', function () {
    config()->set('enrichment.embedding.batch_size', 32);

    $snapshot = latestSnapshotFor($this->link);

    foreach (range(0, 69) as $ordinal) {
        ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'link_id' => $this->link->id, 'ordinal' => $ordinal, 'text' => "chunk {$ordinal}"]);
    }

    Queue::fake();

    app()->call([new EmbedChunksJob($snapshot, 'other-embedding'), 'handle']);

    expect(FakeEmbeddingProvider::$calls)->toHaveCount(1)
        ->and(FakeEmbeddingProvider::$calls[0])->toHaveCount(32)
        ->and(ContentChunk::query()->whereNull('embedding')->count())->toBe(38);

    Queue::assertPushedOn('enrichment', EmbedChunksJob::class, fn (EmbedChunksJob $job): bool => $job->snapshot->is($snapshot) && $job->model === 'other-embedding');
    Queue::assertPushedTimes(EmbedChunksJob::class, 1);
});

test('70 chunks with a batch size of 32 are embedded by three job runs', function () {
    config()->set('enrichment.embedding.batch_size', 32);

    $snapshot = latestSnapshotFor($this->link);

    foreach (range(0, 69) as $ordinal) {
        ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'link_id' => $this->link->id, 'ordinal' => $ordinal, 'text' => "chunk {$ordinal}"]);
    }

    EmbedChunksJob::dispatchSync($snapshot);

    expect(array_map('count', FakeEmbeddingProvider::$calls))->toBe([32, 32, 6])
        ->and(ContentChunk::query()->whereNull('embedding')->count())->toBe(0);
});

test('the enrichment jobs stay inside the database queue retry_after', function () {
    $snapshot = LinkSnapshot::factory()->create();

    expect((new EmbedChunksJob($snapshot))->timeout)->toBe(80)
        ->and((new SummarizeSnapshotJob($snapshot))->timeout)->toBe(60)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(80);
});

test('chunking stops at max_chunks and keeps the full text on the snapshot', function () {
    config()->set('enrichment.chunking.max_chunks', 3);

    $snapshot = latestSnapshotFor($this->link, ['content_text' => longText()]);

    ChunkSnapshotJob::dispatchSync($snapshot);

    expect(ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->pluck('ordinal')->sort()->values()->all())->toBe([0, 1, 2])
        ->and($snapshot->fresh()->content_text)->toBe(longText());
});

test('a stale chunk job that loses the race to a newer snapshot inserts nothing and keeps the new chunks', function () {
    $old = recordText($this->link, longText('old'));
    $new = LinkSnapshot::factory()->create(['link_id' => $this->link->id, 'title' => 'New', 'content_text' => 'New body.']);
    ContentChunk::query()->where('link_snapshot_id', $old->id)->delete();
    ContentChunk::factory()->create(['link_snapshot_id' => $new->id, 'link_id' => $this->link->id, 'ordinal' => 0, 'text' => 'New']);

    /**
     * Once the stale job's isCurrent() check has read the link, the newer
     * snapshot becomes current, as it would if its recorder committed in
     * between.
     */
    $flipped = false;
    Link::retrieved(function (Link $link) use (&$flipped, $new): void {
        if (! $flipped) {
            $flipped = true;
            DB::table('links')->where('id', $link->id)->update(['latest_snapshot_id' => $new->id]);
        }
    });

    ChunkSnapshotJob::dispatchSync($old);

    expect(ContentChunk::query()->where('link_id', $this->link->id)->get(['link_snapshot_id', 'text'])->toArray())
        ->toBe([['link_snapshot_id' => $new->id, 'text' => 'New']]);
});

test('the chunk swap replaces every chunk of the link, from any snapshot, in one step', function () {
    $old = recordText($this->link, longText('old'));
    $new = latestSnapshotFor($this->link, ['title' => 'New', 'content_text' => 'New body.']);

    expect(ContentChunk::query()->where('link_snapshot_id', $old->id)->count())->toBeGreaterThan(0);

    ChunkSnapshotJob::dispatchSync($new);

    expect(ContentChunk::query()->where('link_id', $this->link->id)->pluck('link_snapshot_id')->unique()->all())->toBe([$new->id]);
});

test('a summary from another snapshot with the same content and model is reused without a provider call', function () {
    $otherLink = Link::factory()->create(['user_id' => User::factory()]);
    LinkSnapshot::factory()->create(['link_id' => $otherLink->id, 'content_hash' => str_repeat('a', 64), 'summary' => 'Shared summary', 'summary_model' => 'fake-summary']);
    LinkSnapshot::factory()->create(['link_id' => $otherLink->id, 'content_hash' => str_repeat('a', 64), 'summary' => 'Other model summary', 'summary_model' => 'another-model']);

    $snapshot = latestSnapshotFor($this->link, ['content_hash' => str_repeat('a', 64), 'summary' => null, 'summary_model' => null]);

    SummarizeSnapshotJob::dispatchSync($snapshot);

    expect(FakeSummaryProvider::$calls)->toBe([])
        ->and($snapshot->fresh()->summary)->toBe('Shared summary')
        ->and($snapshot->fresh()->summary_model)->toBe('fake-summary');
});

test('a forced summary is never reused from another snapshot', function () {
    $otherLink = Link::factory()->create(['user_id' => User::factory()]);
    LinkSnapshot::factory()->create(['link_id' => $otherLink->id, 'content_hash' => str_repeat('b', 64), 'summary' => 'Shared summary', 'summary_model' => 'fake-summary']);

    $snapshot = latestSnapshotFor($this->link, ['content_hash' => str_repeat('b', 64), 'summary' => 'Stale', 'summary_model' => 'fake-summary']);

    SummarizeSnapshotJob::dispatchSync($snapshot, force: true);

    expect(FakeSummaryProvider::$calls)->toHaveCount(1)
        ->and($snapshot->fresh()->summary)->toBe("Summary of {$snapshot->title}");
});
