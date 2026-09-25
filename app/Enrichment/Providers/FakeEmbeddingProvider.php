<?php

namespace App\Enrichment\Providers;

use App\Enrichment\Contracts\EmbeddingProvider;

/**
 * The driver used everywhere in the test suite (`EMBEDDING_DRIVER=fake` in
 * phpunit.xml), so no test ever calls a local Ollama server.
 *
 * Vectors are deterministic per text (seeded from `crc32($text)`), so the
 * same text always embeds to the same vector without a real model.
 */
final class FakeEmbeddingProvider implements EmbeddingProvider
{
    private const DEFAULT_MODEL = 'fake-embedding';

    /**
     * Every call to {@see self::embed()}, in call order.
     *
     * @var list<list<string>>
     */
    public static array $calls = [];

    public function __construct(private readonly string $model = self::DEFAULT_MODEL) {}

    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        self::$calls[] = $texts;

        return array_map($this->vectorFor(...), $texts);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return (int) config('enrichment.embedding.ollama.dimensions', 768);
    }

    public function withModel(string $model): static
    {
        return new self($model);
    }

    /**
     * A deterministic, unit-length-ish vector for the given text: a
     * linear-congruential sequence seeded from `crc32($text)`, scaled to
     * [-1, 1].
     *
     * @return list<float>
     */
    private function vectorFor(string $text): array
    {
        $dimensions = $this->dimensions();
        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < $dimensions; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $vector[] = ($seed / 0x7FFFFFFF) * 2 - 1;
        }

        return $vector;
    }
}
