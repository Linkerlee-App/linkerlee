<?php

use App\Health\ContentSimilarity;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->similarity = new ContentSimilarity;
});

/**
 * @return list<string>
 */
function distinctWords(int $count, int $offset = 0): array
{
    return array_map(fn (int $i): string => "word{$i}", range($offset, $offset + $count - 1));
}

test('two empty strings are identical', function () {
    expect($this->similarity->jaccard('', ''))->toBe(1.0);
});

test('one empty string shares nothing with a non-empty one', function () {
    expect($this->similarity->jaccard('', 'hello world'))->toBe(0.0)
        ->and($this->similarity->jaccard('hello world', ''))->toBe(0.0);
});

test('identical text is fully similar', function () {
    $text = implode(' ', distinctWords(1000));

    expect($this->similarity->jaccard($text, $text))->toBe(1.0);
});

test('a changed timestamp line in a 1000-word page stays highly similar', function () {
    $body = implode(' ', distinctWords(1000));

    $before = "Updated at 2024-01-01 12:00:00\n\n{$body}";
    $after = "Updated at 2024-06-15 08:30:45\n\n{$body}";

    expect($this->similarity->jaccard($before, $after))->toBeGreaterThanOrEqual(0.9);
});

test('a rewritten page is not similar', function () {
    $original = implode(' ', distinctWords(1000));
    $rewritten = implode(' ', distinctWords(1000, offset: 1000));

    expect($this->similarity->jaccard($original, $rewritten))->toBeLessThan(0.9);
});

test('comparison is case-insensitive', function () {
    expect($this->similarity->jaccard('Hello World Foo Bar Baz', 'HELLO WORLD FOO BAR BAZ'))->toBe(1.0);
});

test('fewer words than the shingle size is treated as one whole-text shingle', function () {
    expect($this->similarity->jaccard('hello world', 'hello world', k: 5))->toBe(1.0)
        ->and($this->similarity->jaccard('hello world', 'goodbye moon', k: 5))->toBe(0.0);
});

test('the shingle size defaults to config(link_health.shingle_size)', function () {
    config(['link_health.shingle_size' => 3]);

    // "a b c" and "a b d" share the shingle "a b" only once k drops to 2,
    // but at k=3 the two 3-word shingles are wholly different.
    expect($this->similarity->jaccard('a b c', 'a b d'))->toBe(0.0)
        ->and($this->similarity->jaccard('a b c', 'a b d', k: 2))->toBeGreaterThan(0.0);
});

test('case is folded for non-ASCII letters too', function () {
    expect($this->similarity->jaccard('ÄBC', 'äbc'))->toBe(1.0)
        ->and($this->similarity->jaccard('ÉCOLE ÜBER STRAßE', 'école über straße'))->toBe(1.0);
});
