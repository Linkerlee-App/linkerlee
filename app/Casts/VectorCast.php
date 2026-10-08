<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a pgvector column to and from a plain `list<float>`.
 *
 * Postgres represents a vector as the text `'[0.1,0.2,...]'`, so this reads
 * and writes that format directly instead of depending on the `pgvector/pgvector`
 * PHP package.
 *
 * @implements CastsAttributes<list<float>, list<float>>
 */
final class VectorCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<float>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $inner = trim((string) $value);
        $inner = mb_substr($inner, 1, -1);

        if ($inner === '') {
            return [];
        }

        return array_map(static fn (string $component): float => (float) $component, explode(',', $inner));
    }

    /**
     * @param  list<float>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return '['.implode(',', array_map(
            static fn (float $component): string => json_encode($component),
            $value,
        )).']';
    }
}
