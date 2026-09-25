<?php

namespace App\Enrichment\Contracts;

use App\Enrichment\EmbeddingManager;

/**
 * One way of turning content chunk text into embedding vectors.
 *
 * A concrete provider also implements `withModel(string $model): static`,
 * returning a clone configured to use that model instead of the configured
 * default. It is not part of this contract because only
 * {@see EmbeddingManager::provider()} needs it.
 */
interface EmbeddingProvider
{
    /**
     * Embeds each text and returns one vector per text, in input order.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    /**
     * The model this instance embeds with.
     */
    public function model(): string;

    /**
     * The length of every vector this instance is configured to produce.
     */
    public function dimensions(): int;

    /**
     * Embeds one short probe text and returns the length of the vector the
     * model really produces, without checking it against
     * {@see self::dimensions()}; used by `linkerlee:reembed` to compare a
     * model against the column before switching to it.
     */
    public function probeDimensions(): int;
}
