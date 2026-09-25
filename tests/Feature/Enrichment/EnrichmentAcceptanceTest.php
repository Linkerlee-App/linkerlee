<?php

use App\Enums\ExtractionStatus;
use App\Models\ContentChunk;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();
});

afterEach(function () {
    FakeExtractor::reset();
});

/**
 * Four short paragraphs, enough that a snapshot's body is never mistaken for
 * a single unbreakable unit.
 */
function acceptanceBody(string $label): string
{
    return implode("\n\n", array_map(
        fn (int $i): string => "{$label} paragraph {$i}. It has a few sentences of its own text to embed.",
        range(1, 4),
    ));
}

/**
 * Asserts that a link's current snapshot is fully enriched: a summary from
 * the fake summary driver, at least chunk 0 (title/summary) plus one body
 * chunk, and every chunk carrying a 768-float embedding from the fake
 * embedding driver.
 */
function assertFullyEnriched(Link $link): void
{
    $link = $link->fresh();
    $snapshot = $link->latestSnapshot;

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->summary)->not->toBeNull()
        ->and($snapshot->summary_model)->not->toBeNull();

    $chunks = ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->orderBy('ordinal')->get();

    expect($chunks->count())->toBeGreaterThanOrEqual(2);

    foreach ($chunks as $chunk) {
        expect($chunk->embedding)->not->toBeNull()
            ->and($chunk->embedding)->toHaveCount(768)
            ->and($chunk->embedding_model)->not->toBeNull();
    }
}

test('every ok link ends up with a summarized, chunked and embedded current snapshot', function () {
    $user = User::factory()->create();

    $links = collect(range(1, 3))->map(fn (int $i): Link => Link::factory()->create([
        'user_id' => $user->id,
        'link' => "https://example.com/article-{$i}",
        'extraction_status' => ExtractionStatus::Pending,
    ]));

    foreach ($links as $i => $link) {
        FakeExtractor::respondWith(
            $link->link,
            ExtractionResult::ok('fake', acceptanceBody("Article {$i}"), ['title' => "Article {$i} title"]),
        );
    }

    $this->artisan('linkerlee:extract', ['--sync' => true])->assertSuccessful();

    // The sync queue already ran the enrichment chain inline via
    // LinkSnapshotCreated, so these are redundant confirmation passes, not
    // the thing that produces the summaries and chunks.
    $this->artisan('linkerlee:resummarize', ['--sync' => true])->assertSuccessful();
    $this->artisan('linkerlee:rechunk', ['--sync' => true])->assertSuccessful();

    $okLinks = $links->filter(fn (Link $link): bool => $link->fresh()->extraction_status === ExtractionStatus::Ok);

    expect($okLinks)->toHaveCount(3);

    foreach ($okLinks as $link) {
        assertFullyEnriched($link);
    }
});

test('backfill recovers a snapshot whose enrichment never ran', function () {
    $link = Link::factory()->create(['user_id' => User::factory()]);

    $snapshot = LinkSnapshot::factory()->create([
        'link_id' => $link->id,
        'title' => 'Never enriched',
        'content_text' => acceptanceBody('Recovered'),
        'summary' => null,
        'summary_model' => null,
    ]);
    $link->forceFill(['latest_snapshot_id' => $snapshot->id])->save();

    expect(ContentChunk::query()->where('link_snapshot_id', $snapshot->id)->count())->toBe(0);

    $this->artisan('linkerlee:resummarize', ['--sync' => true])->assertSuccessful();

    assertFullyEnriched($link);
});
