<?php

use App\Enrichment\Chunker;
use App\Enrichment\Contracts\EmbeddingProvider;
use App\Enrichment\Contracts\SummaryProvider;
use App\Enrichment\EnrichmentProviderException;
use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Enrichment\Providers\FakeSummaryProvider;
use App\Jobs\ChunkSnapshotJob;
use App\Jobs\EmbedChunksJob;
use App\Jobs\SummarizeSnapshotJob;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

afterEach(function () {
    FakeExtractor::reset();
    FakeSummaryProvider::reset();
    FakeEmbeddingProvider::reset();
});

/**
 * A link whose factory snapshot is set as its `latest_snapshot_id`, without
 * firing the real enrichment chain.
 */
function makeCurrentSnapshot(array $attributes = []): LinkSnapshot
{
    $link = Link::factory()->create(['user_id' => User::factory()]);
    $snapshot = LinkSnapshot::factory()->create(['link_id' => $link->id, ...$attributes]);
    $link->forceFill(['latest_snapshot_id' => $snapshot->id])->save();

    return $snapshot;
}

function makeChunkFor(LinkSnapshot $snapshot, array $attributes = []): ContentChunk
{
    return ContentChunk::factory()->create(['link_snapshot_id' => $snapshot->id, 'link_id' => $snapshot->link_id, ...$attributes]);
}

// ---------------------------------------------------------------------
// LinkSnapshot::scopeCurrent()
// ---------------------------------------------------------------------

test('the current scope excludes a superseded snapshot and includes the latest one', function () {
    $link = Link::factory()->create(['user_id' => User::factory()]);
    $superseded = LinkSnapshot::factory()->create(['link_id' => $link->id]);
    $latest = LinkSnapshot::factory()->create(['link_id' => $link->id]);
    $link->forceFill(['latest_snapshot_id' => $latest->id])->save();

    $current = LinkSnapshot::query()->current()->pluck('id')->all();

    expect($current)->toContain($latest->id)
        ->and($current)->not->toContain($superseded->id);
});

test('the current scope excludes the latest snapshot of a trashed link', function () {
    $snapshot = makeCurrentSnapshot();
    Link::find($snapshot->link_id)->delete();

    expect(LinkSnapshot::query()->current()->pluck('id')->all())->not->toContain($snapshot->id);
});

// ---------------------------------------------------------------------
// linkerlee:resummarize
// ---------------------------------------------------------------------

test('resummarize dispatches SummarizeSnapshotJob alone for a snapshot that already has chunks', function () {
    Queue::fake();

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 snapshot to the enrichment queue.');

    Queue::assertPushedOn('enrichment', SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->snapshot->is($snapshot)
        && $job->model === null
        && $job->force === false);
    Queue::assertNotPushed(ChunkSnapshotJob::class);
});

test('resummarize dispatches the summary job and a separate chunk-then-embed chain for a snapshot with no chunks yet', function () {
    Bus::fake();

    $snapshot = makeCurrentSnapshot();

    $this->artisan('linkerlee:resummarize')->assertSuccessful();

    Bus::assertDispatched(SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->snapshot->is($snapshot)
        && $job->queue === 'enrichment'
        && $job->chained === []);
    Bus::assertChained([
        ChunkSnapshotJob::class,
        EmbedChunksJob::class,
    ]);
});

test('resummarize skips a snapshot already summarized by the target model', function () {
    Queue::fake();

    makeCurrentSnapshot(['summary' => 'Existing summary', 'summary_model' => 'fake-summary']);

    $this->artisan('linkerlee:resummarize')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 0 snapshots to the enrichment queue.');

    Queue::assertNothingPushed();
});

test('resummarize --all forces every current snapshot, even one already matching the model', function () {
    Queue::fake();

    $snapshot = makeCurrentSnapshot(['summary' => 'Existing summary', 'summary_model' => 'fake-summary']);
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize', ['--all' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 1 snapshot to the enrichment queue.');

    Queue::assertPushedOn('enrichment', SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->snapshot->is($snapshot) && $job->force === true);
});

test('resummarize --all on a snapshot with no chunks yet dispatches a forced Summarize beside the Chunk and Embed chain', function () {
    Bus::fake();

    $snapshot = makeCurrentSnapshot(['summary' => 'Existing summary', 'summary_model' => 'fake-summary']);

    $this->artisan('linkerlee:resummarize', ['--all' => true])->assertSuccessful();

    Bus::assertChained([
        ChunkSnapshotJob::class,
        EmbedChunksJob::class,
    ]);
    Bus::assertDispatched(SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->snapshot->is($snapshot) && $job->force === true);
});

test('resummarize --model rewrites chunk 0 with the new summary and re-embeds only chunk 0', function () {
    $snapshot = makeCurrentSnapshot(['title' => 'Kept title', 'summary' => 'Old summary', 'summary_model' => 'fake-summary', 'content_text' => "Body one.\n\nBody two."]);

    $this->artisan('linkerlee:rechunk', ['--sync' => true])->assertSuccessful();

    expect(ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->count())->toBeGreaterThan(1)
        ->and(ContentChunk::query()->where('ordinal', 0)->sole()->text)->toBe("Kept title\n\nOld summary");

    FakeEmbeddingProvider::reset();

    $this->artisan('linkerlee:resummarize', ['--model' => 'x', '--sync' => true])->assertSuccessful();

    $head = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->where('ordinal', 0)->sole();

    expect($head->text)->toContain('Summary of Kept title')
        ->and($head->embedding_model)->toBe('fake-embedding')
        ->and(FakeEmbeddingProvider::$calls)->toBe([["Kept title\n\nSummary of Kept title"]])
        ->and(ContentChunk::query()->whereNull('embedding')->count())->toBe(0);
});

test('resummarize with the anthropic driver and no api key exits 1 and dispatches nothing', function () {
    Queue::fake();
    Bus::fake();
    config()->set('enrichment.summary.driver', 'anthropic');
    config()->set('enrichment.summary.anthropic.api_key', '');

    makeCurrentSnapshot();

    $this->artisan('linkerlee:resummarize')
        ->assertExitCode(1)
        ->expectsOutputToContain('ANTHROPIC_API_KEY');

    Queue::assertNothingPushed();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('resummarize --model overrides the target model and is passed through to the job', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize', ['--model' => 'other-model', '--sync' => true])->assertSuccessful();

    expect($snapshot->fresh()->summary_model)->toBe('other-model');
});

test('resummarize --sync summarizes inline', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Resummarized 1 snapshot (1 ok, 0 failed).');

    expect($snapshot->fresh()->summary)->not->toBeNull()
        ->and(FakeSummaryProvider::$calls)->toHaveCount(1);
});

test('resummarize is idempotent: a second sync run dispatches nothing and calls the provider no further', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize', ['--sync' => true])->assertSuccessful();
    expect(FakeSummaryProvider::$calls)->toHaveCount(1);

    FakeSummaryProvider::reset();

    $this->artisan('linkerlee:resummarize', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Resummarized 0 snapshots (0 ok, 0 failed).');

    expect(FakeSummaryProvider::$calls)->toBe([]);
});

test('resummarize never refetches: FakeExtractor sees no calls', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:resummarize', ['--sync' => true])->assertSuccessful();

    expect(FakeExtractor::$calls)->toBe([]);
});

test('resummarize ignores a trashed link even though latest_snapshot_id still points at its snapshot', function () {
    Queue::fake();

    $snapshot = makeCurrentSnapshot();
    Link::find($snapshot->link_id)->delete();

    $this->artisan('linkerlee:resummarize')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 0 snapshots to the enrichment queue.');

    Queue::assertNothingPushed();
});

// ---------------------------------------------------------------------
// linkerlee:rechunk
// ---------------------------------------------------------------------

test('rechunk chains ChunkSnapshotJob then EmbedChunksJob for every current snapshot', function () {
    Bus::fake();

    makeCurrentSnapshot();
    makeCurrentSnapshot();

    $this->artisan('linkerlee:rechunk')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 2 snapshots to the enrichment queue.');

    Bus::assertChained([ChunkSnapshotJob::class, EmbedChunksJob::class]);
    Bus::assertDispatchedTimes(ChunkSnapshotJob::class, 2);
});

test('rechunk --sync rebuilds chunks and embeddings inline', function () {
    $snapshot = makeCurrentSnapshot(['title' => 'Title', 'summary' => null, 'content_text' => 'Body text.']);

    $this->artisan('linkerlee:rechunk', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Rechunked 1 snapshot (1 ok, 0 failed).');

    $chunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->get();

    expect($chunks)->not->toBeEmpty();

    foreach ($chunks as $chunk) {
        expect($chunk->embedding)->not->toBeNull();
    }
});

test('rechunk is idempotent: two sync runs leave identical chunk content', function () {
    $snapshot = makeCurrentSnapshot(['title' => 'Title', 'summary' => null, 'content_text' => 'Body text for rechunking.']);

    $this->artisan('linkerlee:rechunk', ['--sync' => true])->assertSuccessful();

    $first = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)
        ->orderBy('ordinal')
        ->get(['ordinal', 'text', 'token_count', 'embedding', 'embedding_model'])
        ->toArray();

    $this->artisan('linkerlee:rechunk', ['--sync' => true])->assertSuccessful();

    $second = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)
        ->orderBy('ordinal')
        ->get(['ordinal', 'text', 'token_count', 'embedding', 'embedding_model'])
        ->toArray();

    expect($second)->toBe($first);
});

test('rechunk never refetches: FakeExtractor sees no calls', function () {
    makeCurrentSnapshot();

    $this->artisan('linkerlee:rechunk', ['--sync' => true])->assertSuccessful();

    expect(FakeExtractor::$calls)->toBe([]);
});

// ---------------------------------------------------------------------
// linkerlee:reembed
// ---------------------------------------------------------------------

test('reembed dispatches EmbedChunksJob only for snapshots with a chunk missing an embedding or on another model', function () {
    Queue::fake();

    $vector = array_fill(0, 768, 0.1);

    $upToDate = makeCurrentSnapshot();
    makeChunkFor($upToDate, ['embedding' => $vector, 'embedding_model' => 'fake-embedding']);

    $stale = makeCurrentSnapshot();
    makeChunkFor($stale, ['embedding' => $vector, 'embedding_model' => 'old-model']);

    $missing = makeCurrentSnapshot();
    makeChunkFor($missing, ['embedding' => null, 'embedding_model' => null]);

    $this->artisan('linkerlee:reembed')
        ->assertSuccessful()
        ->expectsOutputToContain('Dispatched 2 snapshots to the enrichment queue.');

    Queue::assertPushedOn('enrichment', EmbedChunksJob::class, fn (EmbedChunksJob $job): bool => $job->snapshot->is($stale));
    Queue::assertPushedOn('enrichment', EmbedChunksJob::class, fn (EmbedChunksJob $job): bool => $job->snapshot->is($missing));
    Queue::assertPushedTimes(EmbedChunksJob::class, 2);
    Queue::assertNotPushed(EmbedChunksJob::class, fn (EmbedChunksJob $job): bool => $job->snapshot->is($upToDate));
});

test('reembed --sync embeds inline and --model sets embedding_model on every re-embedded chunk', function () {
    $snapshot = makeCurrentSnapshot();
    $chunk = makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed', ['--model' => 'other-embedding', '--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Embedded 1 snapshot (1 ok, 0 failed).');

    expect($chunk->fresh()->embedding_model)->toBe('other-embedding');
});

test('reembed is idempotent: a second sync run dispatches nothing and calls the provider no further', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed', ['--sync' => true])->assertSuccessful();
    expect(FakeEmbeddingProvider::$calls)->toHaveCount(1);

    FakeEmbeddingProvider::reset();

    $this->artisan('linkerlee:reembed', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Embedded 0 snapshots (0 ok, 0 failed).');

    expect(FakeEmbeddingProvider::$calls)->toBe([]);
});

test('reembed refuses and prints a migration when the column dimension does not match the provider', function () {
    Queue::fake();
    config()->set('enrichment.embedding.ollama.dimensions', 1024);

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('vector(1024)');

    Queue::assertNothingPushed();
});

/**
 * Binds the fake embedding driver to a provider that reports
 * `$configuredDimensions` (EMBEDDING_DIMENSIONS) but whose model really
 * produces vectors of `$actualDimensions`, optionally throwing from the probe
 * or from embedding itself.
 */
function bindEmbeddingProvider(int $actualDimensions, ?Throwable $probeFailure = null, ?Throwable $embedFailure = null, int $configuredDimensions = 768): void
{
    app()->bind(FakeEmbeddingProvider::class, fn (): EmbeddingProvider => new class($actualDimensions, $probeFailure, $embedFailure, $configuredDimensions) implements EmbeddingProvider
    {
        public function __construct(private int $actual, private ?Throwable $probeFailure, private ?Throwable $embedFailure, private int $configured) {}

        public function embed(array $texts): array
        {
            if ($this->embedFailure !== null) {
                throw $this->embedFailure;
            }

            return array_map(fn (): array => array_fill(0, $this->actual, 0.1), $texts);
        }

        public function probeDimensions(): int
        {
            if ($this->probeFailure !== null) {
                throw $this->probeFailure;
            }

            return $this->actual;
        }

        public function model(): string
        {
            return 'fake-embedding';
        }

        public function dimensions(): int
        {
            return $this->configured;
        }

        public function withModel(string $model): static
        {
            return $this;
        }
    });
}

test('reembed refuses when EMBEDDING_DIMENSIONS disagrees with what the model really produces, even though the column matches', function () {
    Queue::fake();
    bindEmbeddingProvider(768, configuredDimensions: 1024);

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('Set EMBEDDING_DIMENSIONS=768');

    Queue::assertNothingPushed();
    expect(FakeEmbeddingProvider::$calls)->toBe([]);
});

test('reembed refuses when the model really produces another size, even though the config says 768', function () {
    Queue::fake();
    bindEmbeddingProvider(1024);

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('the target model produces vector(1024)');

    Queue::assertNothingPushed();
});

test('reembed exits 1 with a clear error when the dimension probe fails', function () {
    Queue::fake();
    bindEmbeddingProvider(768, probeFailure: new EnrichmentProviderException('Ollama is down'));

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('Could not probe the embedding model fake-embedding: Ollama is down');

    Queue::assertNothingPushed();
});

test('reembed reports a missing content_chunks table instead of a raw exception', function () {
    Queue::fake();
    Schema::drop('content_chunks');

    makeCurrentSnapshot();

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('has the content_chunks migration run?');

    Queue::assertNothingPushed();
});

test('resummarize --sync counts a failing snapshot and carries on with the rest', function () {
    $failing = makeCurrentSnapshot(['title' => 'Explodes']);
    makeChunkFor($failing);
    $working = makeCurrentSnapshot(['title' => 'Works']);
    makeChunkFor($working);

    app()->bind(FakeSummaryProvider::class, fn (): SummaryProvider => new class implements SummaryProvider
    {
        public function summarize(string $title, string $text): string
        {
            if ($title === 'Explodes') {
                throw new EnrichmentProviderException('Anthropic is down');
            }

            return "Summary of {$title}";
        }

        public function model(): string
        {
            return 'fake-summary';
        }
    });

    $this->artisan('linkerlee:resummarize', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Resummarized 2 snapshots (1 ok, 1 failed).');

    expect($working->fresh()->summary)->toBe('Summary of Works')
        ->and($failing->fresh()->summary)->toBeNull();
});

test('resummarize --sync still chunks and embeds a chunkless snapshot whose summary fails, and counts it failed', function () {
    $snapshot = makeCurrentSnapshot(['title' => 'Explodes', 'content_text' => 'Body.']);

    app()->bind(FakeSummaryProvider::class, fn (): SummaryProvider => new class implements SummaryProvider
    {
        public function summarize(string $title, string $text): string
        {
            throw new EnrichmentProviderException('Anthropic is down');
        }

        public function model(): string
        {
            return 'fake-summary';
        }
    });

    $this->artisan('linkerlee:resummarize', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Resummarized 1 snapshot (0 ok, 1 failed).');

    expect(ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->whereNotNull('embedding')->count())->toBe(2);
});

test('rechunk --sync counts a failing snapshot and carries on with the rest', function () {
    makeCurrentSnapshot();
    makeCurrentSnapshot();

    $calls = 0;
    app()->bind(Chunker::class, function () use (&$calls): Chunker {
        if (++$calls === 1) {
            throw new RuntimeException('Chunker blew up');
        }

        return new Chunker;
    });

    $this->artisan('linkerlee:rechunk', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Rechunked 2 snapshots (1 ok, 1 failed).');

    expect(ContentChunk::query()->distinct()->count('link_snapshot_id'))->toBe(1);
});

test('reembed --sync counts a failing snapshot', function () {
    bindEmbeddingProvider(768, embedFailure: new EnrichmentProviderException('Ollama is down'));

    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed', ['--sync' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Embedded 1 snapshot (0 ok, 1 failed).');
});

test('reembed guards a missing embedding column with a clear error instead of a raw exception', function () {
    Queue::fake();
    DB::statement('alter table content_chunks drop column embedding');

    makeCurrentSnapshot();

    $this->artisan('linkerlee:reembed')
        ->assertExitCode(1)
        ->expectsOutputToContain('content_chunks.embedding column not found');

    Queue::assertNothingPushed();
});

test('reembed never refetches: FakeExtractor sees no calls', function () {
    $snapshot = makeCurrentSnapshot();
    makeChunkFor($snapshot);

    $this->artisan('linkerlee:reembed', ['--sync' => true])->assertSuccessful();

    expect(FakeExtractor::$calls)->toBe([]);
});
