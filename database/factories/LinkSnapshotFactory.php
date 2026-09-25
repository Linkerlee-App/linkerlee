<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkSnapshot>
 */
class LinkSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The hash and word count are derived from `content_text`, so a state that
     * overrides the text keeps them consistent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => Link::factory()->state(['user_id' => User::factory()]),
            'extractor' => 'fake',
            'http_status' => 200,
            'final_url' => $this->faker->url(),
            'title' => $this->faker->sentence(4),
            'content_text' => $this->faker->paragraphs(3, true),
            'content_hash' => fn (array $attributes): string => hash('sha256', $attributes['content_text']),
            'word_count' => fn (array $attributes): int => count(preg_split('/\s+/u', trim($attributes['content_text']), -1, PREG_SPLIT_NO_EMPTY)),
            'fetched_at' => now(),
        ];
    }
}
