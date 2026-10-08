<?php

namespace App\Enrichment;

/**
 * Splits a snapshot's title, summary and article text into the chunks that
 * are embedded for semantic search. Pure: no database, no HTTP.
 *
 * Tokens are estimated as `ceil(mb_strlen / chars_per_token)`, so budgets are
 * worked in characters without a real tokenizer.
 *
 * Chunk 0 is the title and summary. The text follows, split into paragraphs
 * on blank lines and packed whole into chunks of about `size_tokens`. Every
 * chunk after the first body chunk starts with about `overlap_tokens` carried
 * from the tail of the previous one, so a chunk can exceed `size_tokens` by
 * at most that overlap. A paragraph larger than `size_tokens` is split on
 * sentence boundaries, and a sentence that is still too large is cut into
 * hard character slices.
 *
 * At most `max_chunks` chunks (chunk 0 included) are returned; the rest of an
 * enormous page is left out of search, though its full text stays on the
 * snapshot.
 */
final class Chunker
{
    private readonly int $sizeTokens;

    private readonly int $overlapTokens;

    private readonly int $charsPerToken;

    private readonly int $maxChunks;

    public function __construct(?int $sizeTokens = null, ?int $overlapTokens = null, ?int $charsPerToken = null, ?int $maxChunks = null)
    {
        $this->sizeTokens = max(1, $sizeTokens ?? (int) config('enrichment.chunking.size_tokens'));
        $this->overlapTokens = max(0, $overlapTokens ?? (int) config('enrichment.chunking.overlap_tokens'));
        $this->charsPerToken = max(1, $charsPerToken ?? (int) config('enrichment.chunking.chars_per_token'));
        $this->maxChunks = max(1, $maxChunks ?? (int) config('enrichment.chunking.max_chunks', 500));
    }

    /**
     * @return list<array{text: string, token_count: int}>
     */
    public function chunk(string $title, ?string $summary, string $text): array
    {
        $head = $this->head($title, $summary);

        $chunks = [
            ...($head === null ? [] : [$head]),
            ...array_map($this->withTokenCount(...), $this->pack($this->units($text))),
        ];

        return array_slice($chunks, 0, $this->maxChunks);
    }

    /**
     * Chunk 0: the title and summary, or null when both are empty (the body
     * then starts at chunk 0).
     *
     * @return array{text: string, token_count: int}|null
     */
    public function head(string $title, ?string $summary): ?array
    {
        $head = trim($summary === null ? $title : "{$title}\n\n{$summary}");

        return $head === '' ? null : $this->withTokenCount($head);
    }

    /**
     * @return array{text: string, token_count: int}
     */
    private function withTokenCount(string $chunk): array
    {
        return ['text' => $chunk, 'token_count' => $this->tokens($chunk)];
    }

    /**
     * Packs the units into chunks of at most `size_tokens` (plus the carried
     * overlap when a single unit and its overlap do not fit together). A
     * chunk always takes at least one unit, so the packing always advances.
     *
     * @param  list<string>  $units
     * @return list<string>
     */
    private function pack(array $units): array
    {
        $sizeChars = $this->sizeTokens * $this->charsPerToken;
        $chunks = [];
        $overlap = '';
        $current = [];

        foreach ($units as $unit) {
            $candidate = self::join($overlap, [...$current, $unit]);

            if ($current !== [] && mb_strlen($candidate) > $sizeChars) {
                $chunk = self::join($overlap, $current);
                $chunks[] = $chunk;
                $overlap = $this->tail($chunk);
                $current = [$unit];

                continue;
            }

            $current[] = $unit;
        }

        if ($current !== []) {
            $chunks[] = self::join($overlap, $current);
        }

        return $chunks;
    }

    /**
     * The text's paragraphs, with any paragraph larger than `size_tokens`
     * split into sentence-packed pieces.
     *
     * @return list<string>
     */
    private function units(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [];
        $units = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if ($this->tokens($paragraph) <= $this->sizeTokens) {
                $units[] = $paragraph;

                continue;
            }

            $units = [...$units, ...$this->splitOversize($paragraph)];
        }

        return $units;
    }

    /**
     * Splits an oversize paragraph on sentence boundaries and packs the
     * sentences back into pieces of at most `size_tokens`. A sentence that
     * alone exceeds it is cut into hard character slices.
     *
     * @return list<string>
     */
    private function splitOversize(string $paragraph): array
    {
        $sizeChars = $this->sizeTokens * $this->charsPerToken;
        $sentences = preg_split('/(?<=[.!?])\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [$paragraph];
        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($sentence) > $sizeChars) {
                if ($current !== '') {
                    $pieces[] = $current;
                    $current = '';
                }

                for ($offset = 0; $offset < mb_strlen($sentence); $offset += $sizeChars) {
                    $pieces[] = mb_substr($sentence, $offset, $sizeChars);
                }

                continue;
            }

            $candidate = $current === '' ? $sentence : "{$current} {$sentence}";

            if (mb_strlen($candidate) > $sizeChars) {
                $pieces[] = $current;
                $current = $sentence;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * About `overlap_tokens` from the end of the chunk. When the cut lands
     * inside a word, the partial word is dropped so the overlap starts on a
     * word boundary; a tail with no whitespace at all (one long token) is
     * kept as a hard character tail.
     */
    private function tail(string $chunk): string
    {
        $overlapChars = $this->overlapTokens * $this->charsPerToken;

        if ($overlapChars === 0) {
            return '';
        }

        $length = mb_strlen($chunk);

        if ($length <= $overlapChars) {
            return trim($chunk);
        }

        $tail = mb_substr($chunk, -$overlapChars);
        $cutMidWord = preg_match('/\S/u', mb_substr($chunk, $length - $overlapChars - 1, 1)) === 1;

        if ($cutMidWord && preg_match('/\s/u', $tail, $match, PREG_OFFSET_CAPTURE) === 1) {
            $tail = substr($tail, $match[0][1]);
        }

        return trim($tail);
    }

    /**
     * The overlap followed by the units, separated by blank lines.
     *
     * @param  list<string>  $units
     */
    private static function join(string $overlap, array $units): string
    {
        return implode("\n\n", array_filter([$overlap, ...$units], fn (string $part): bool => $part !== ''));
    }

    private function tokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / $this->charsPerToken);
    }
}
