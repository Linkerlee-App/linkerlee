<?php

namespace App\Health;

use App\Scraping\ExtractionResult;

/**
 * Jaccard similarity over k-word shingles, used to tell a real content
 * change from cosmetic noise (a timestamp, an ad slot, a view counter).
 */
class ContentSimilarity
{
    /**
     * The fraction of shared shingles between two texts, from 0.0 (nothing
     * in common) to 1.0 (identical shingle sets). Two empty strings are
     * identical and give 1.0; one empty and one non-empty string share
     * nothing and give 0.0.
     */
    public function jaccard(string $a, string $b, ?int $k = null): float
    {
        $k = $k ?? (int) config('link_health.shingle_size');

        $shinglesA = $this->shingles($a, $k);
        $shinglesB = $this->shingles($b, $k);

        $shared = count(array_intersect_key(array_flip($shinglesA), array_flip($shinglesB)));

        return $shared / (count($shinglesA) + count($shinglesB) - $shared);
    }

    /**
     * The text's distinct k-word shingles, case-folded (multibyte-aware) and
     * whitespace-normalized. Text shorter than k words is one shingle.
     *
     * @return list<string>
     */
    private function shingles(string $text, int $k): array
    {
        $normalized = ExtractionResult::normalize(mb_strtolower($text));
        $words = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) < $k) {
            return [implode(' ', $words)];
        }

        $shingles = [];

        for ($i = 0, $last = count($words) - $k; $i <= $last; $i++) {
            $shingles[] = implode(' ', array_slice($words, $i, $k));
        }

        return array_values(array_unique($shingles));
    }
}
