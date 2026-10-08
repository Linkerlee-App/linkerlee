<?php

use App\Enrichment\Chunker;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    /**
     * 50 tokens of 4 chars = 200 chars per chunk, 10 tokens = 40 chars of overlap.
     */
    $this->chunker = new Chunker(sizeTokens: 50, overlapTokens: 10, charsPerToken: 4);
});

/**
 * A paragraph of at most the given length, made of distinct numbered words
 * so that each paragraph (and each tail of one) is recognisable.
 */
function paragraphOfLength(string $label, int $length): string
{
    $words = [];
    $i = 0;

    while (mb_strlen(implode(' ', $words)) < $length) {
        $words[] = "{$label}w{$i}";
        $i++;
    }

    return rtrim(mb_substr(implode(' ', $words), 0, $length));
}

test('chunk 0 is the title and summary', function () {
    $chunks = $this->chunker->chunk('My Title', 'A short summary.', '');

    expect($chunks)->toBe([
        ['text' => "My Title\n\nA short summary.", 'token_count' => 7],
    ]);
});

test('chunk 0 is only the title when there is no summary', function () {
    $chunks = $this->chunker->chunk('My Title', null, 'Body text.');

    expect($chunks[0])->toBe(['text' => 'My Title', 'token_count' => 2])
        ->and($chunks[1]['text'])->toBe('Body text.');
});

test('chunk 0 is skipped when both the title and the summary are empty', function () {
    $chunks = $this->chunker->chunk('', null, 'Body text.');

    expect($chunks)->toBe([
        ['text' => 'Body text.', 'token_count' => 3],
    ]);
});

test('empty text gives only chunk 0', function () {
    expect($this->chunker->chunk('Title', 'Summary', ''))->toHaveCount(1)
        ->and($this->chunker->chunk('Title', 'Summary', "  \n\n\n  "))->toHaveCount(1);
});

test('nothing at all gives no chunks', function () {
    expect($this->chunker->chunk('', null, ''))->toBe([]);
});

test('paragraphs that fit are packed together and kept whole', function () {
    $first = paragraphOfLength('a', 60);
    $second = paragraphOfLength('b', 60);
    $third = paragraphOfLength('c', 60);
    $fourth = paragraphOfLength('d', 60);

    $chunks = $this->chunker->chunk('', null, implode("\n\n\n", [$first, $second, $third, $fourth]));

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]['text'])->toBe("{$first}\n\n{$second}\n\n{$third}")
        ->and($chunks[1]['text'])->toEndWith("\n\n{$fourth}");

    foreach ([$first, $second, $third, $fourth] as $paragraph) {
        $containing = array_filter($chunks, fn (array $chunk): bool => str_contains($chunk['text'], $paragraph));

        expect($containing)->not->toBeEmpty();
    }
});

test('each chunk carries about overlap_tokens of the previous chunk tail, cut on a word boundary', function () {
    $paragraphs = array_map(fn (string $label): string => paragraphOfLength($label, 90), ['a', 'b', 'c', 'd', 'e']);

    $chunks = $this->chunker->chunk('', null, implode("\n\n", $paragraphs));

    expect(count($chunks))->toBeGreaterThan(1);

    for ($i = 1; $i < count($chunks); $i++) {
        $previous = $chunks[$i - 1]['text'];
        $overlap = explode("\n\n", $chunks[$i]['text'])[0];

        expect($previous)->toEndWith($overlap)
            ->and(mb_strlen($overlap))->toBeGreaterThan(0)
            ->and(mb_strlen($overlap))->toBeLessThanOrEqual(40)
            ->and(mb_substr($previous, -mb_strlen($overlap) - 1, 1))->toMatch('/\s/');
    }
});

test('a paragraph larger than the chunk size is split on sentence boundaries', function () {
    $sentences = array_map(fn (int $i): string => "Sentence number {$i} says something short.", range(1, 20));
    $paragraph = implode(' ', $sentences);

    expect((int) ceil(mb_strlen($paragraph) / 4))->toBeGreaterThan(50);

    $chunks = $this->chunker->chunk('', null, $paragraph);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk['token_count'])->toBeLessThanOrEqual((int) ceil((200 + 2 + 40) / 4));
    }

    foreach ($sentences as $sentence) {
        $containing = array_filter($chunks, fn (array $chunk): bool => str_contains($chunk['text'], $sentence));

        expect($containing)->not->toBeEmpty();
    }
});

test('a sentence larger than the chunk size falls back to hard character splits', function () {
    $blob = str_repeat('x', 450);

    $chunks = $this->chunker->chunk('', null, $blob);

    expect(count($chunks))->toBeGreaterThanOrEqual(3);

    $withoutOverlap = array_map(fn (array $chunk): string => last(explode("\n\n", $chunk['text'])), $chunks);

    expect(implode('', $withoutOverlap))->toBe($blob);

    foreach ($chunks as $chunk) {
        expect($chunk['token_count'])->toBeLessThanOrEqual((int) ceil((200 + 2 + 40) / 4));
    }
});

test('token_count is ceil(mb_strlen / chars_per_token) of the chunk text', function () {
    $chunks = $this->chunker->chunk('Tïtle', 'Sümmary', "Päragraph one.\n\nParagraph two is here.");

    foreach ($chunks as $chunk) {
        expect($chunk['token_count'])->toBe((int) ceil(mb_strlen($chunk['text']) / 4));
    }
});

test('defaults come from the enrichment.chunking config', function () {
    config()->set('enrichment.chunking.size_tokens', 5);
    config()->set('enrichment.chunking.overlap_tokens', 1);
    config()->set('enrichment.chunking.chars_per_token', 2);

    $chunks = (new Chunker)->chunk('', null, "one two\n\nthree four\n\nfive six");

    expect($chunks)->toHaveCount(3);
});

test('stops after max_chunks, counting chunk 0', function () {
    $chunker = new Chunker(sizeTokens: 50, overlapTokens: 10, charsPerToken: 4, maxChunks: 3);
    $text = implode("\n\n", array_map(fn (int $i): string => paragraphOfLength("p{$i}", 180), range(1, 6)));

    $chunks = $chunker->chunk('Title', null, $text);

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0]['text'])->toBe('Title')
        ->and($this->chunker->chunk('Title', null, $text))->toHaveCount(7);
});
