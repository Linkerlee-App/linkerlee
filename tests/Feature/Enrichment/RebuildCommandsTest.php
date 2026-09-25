<?php

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

test('resummarize chains the full pipeline for a snapshot with no chunks yet', function () {
    Bus::fake();

    makeCurrentSnapshot();

    $this->artisan('linkerlee:resummarize')->assertSuccessful();

    Bus::assertChained([
        SummarizeSnapshotJob::class,
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

test('resummarize --all on a snapshot with no chunks yet chains a forced Summarize into Chunk and Embed', function () {
    Bus::fake();

    $snapshot = makeCurrentSnapshot(['summary' => 'Existing summary', 'summary_model' => 'fake-summary']);

    $this->artisan('linkerlee:resummarize', ['--all' => true])->assertSuccessful();

    Bus::assertChained([
        SummarizeSnapshotJob::class,
        ChunkSnapshotJob::class,
        EmbedChunksJob::class,
    ]);
    Bus::assertDispatched(SummarizeSnapshotJob::class, fn (SummarizeSnapshotJob $job): bool => $job->snapshot->is($snapshot) && $job->force === true);
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
        ->expectsOutputToContain('Resummarized 1 snapshot.');

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
        ->expectsOutputToContain('Resummarized 0 snapshots.');

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
        ->expectsOutputToContain('Rechunked 1 snapshot.');

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
        ->expectsOutputToContain('Embedded 1 snapshot.');

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
        ->expectsOutputToContain('Embedded 0 snapshots.');

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
