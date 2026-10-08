<?php

use App\Casts\VectorCast;
use App\Models\ContentChunk;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('set serialises a float list to the pgvector text format', function () {
    $cast = new VectorCast;
    $model = new ContentChunk;

    $serialised = $cast->set($model, 'embedding', [0.1, 0.2, -0.3], []);

    expect($serialised)->toBe('[0.1,0.2,-0.3]');
});

test('get parses the pgvector text format back into a float list', function () {
    $cast = new VectorCast;
    $model = new ContentChunk;

    $vector = $cast->get($model, 'embedding', '[0.1,0.2,-0.3]', []);

    expect($vector)->toBe([0.1, 0.2, -0.3]);
});

test('set then get round-trips an arbitrary vector exactly', function () {
    $cast = new VectorCast;
    $model = new ContentChunk;

    $original = array_map(fn (int $i): float => sin($i) / 2, range(1, 768));

    $roundTripped = $cast->get($model, 'embedding', $cast->set($model, 'embedding', $original, []), []);

    expect($roundTripped)->toBe($original);
});

test('null passes through both directions', function () {
    $cast = new VectorCast;
    $model = new ContentChunk;

    expect($cast->set($model, 'embedding', null, []))->toBeNull()
        ->and($cast->get($model, 'embedding', null, []))->toBeNull();
});
