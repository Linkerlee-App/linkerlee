<?php

namespace Database\Factories;

use App\Models\ContentChunk;
use App\Models\LinkSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentChunk>
 */
class ContentChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `link_id` is derived from `link_snapshot_id` (once it has been
     * created, that key holds the snapshot's id) so a chunk is never left
     * pointing at a different link than its own snapshot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_snapshot_id' => LinkSnapshot::factory(),
            'link_id' => fn (array $attributes): int => LinkSnapshot::findOrFail($attributes['link_snapshot_id'])->link_id,
            'ordinal' => 0,
            'text' => $this->faker->paragraph(),
            'token_count' => fn (array $attributes): int => (int) ceil(mb_strlen((string) $attributes['text']) / 4),
            'embedding' => null,
            'embedding_model' => null,
        ];
    }
}
