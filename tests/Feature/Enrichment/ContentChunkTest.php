<?php

use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('enrichment.embedding.ollama.dimensions', 768);
});

test('a chunk with a 768-dim fake vector persists on postgres and round-trips exactly', function () {
    $vector = (new FakeEmbeddingProvider)->embed(['some chunk text'])[0];

    expect($vector)->toHaveCount(768);

    $chunk = ContentChunk::factory()->create([
        'embedding' => $vector,
        'embedding_model' => 'fake-embedding',
    ]);

    $fresh = ContentChunk::query()->findOrFail($chunk->id);

    // Postgres' vector type stores single-precision (float4) components, so
    // a round trip loses precision beyond ~6-7 significant digits.
    expect($fresh->embedding)->toHaveCount(768)
        ->and($fresh->embedding)->toEqualWithDelta($vector, 0.0001)
        ->and($fresh->embedding_model)->toBe('fake-embedding');
});

test('a chunk without an embedding persists with a null vector', function () {
    $chunk = ContentChunk::factory()->create(['embedding' => null]);

    expect(ContentChunk::query()->findOrFail($chunk->id)->embedding)->toBeNull();
});

test('deleting a link snapshot cascades to its chunks', function () {
    $snapshot = LinkSnapshot::factory()->create();
    $chunk = ContentChunk::factory()->create([
        'link_snapshot_id' => $snapshot->id,
        'link_id' => $snapshot->link_id,
    ]);

    $snapshot->delete();

    expect(ContentChunk::query()->find($chunk->id))->toBeNull();
});

test('the same ordinal cannot be used twice for one snapshot', function () {
    $snapshot = LinkSnapshot::factory()->create();

    ContentChunk::factory()->create([
        'link_snapshot_id' => $snapshot->id,
        'link_id' => $snapshot->link_id,
        'ordinal' => 0,
    ]);

    expect(fn () => ContentChunk::factory()->create([
        'link_snapshot_id' => $snapshot->id,
        'link_id' => $snapshot->link_id,
        'ordinal' => 0,
    ]))->toThrow(QueryException::class);
});
