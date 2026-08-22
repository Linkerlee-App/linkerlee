<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(2, true),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Give the collection tag rules.
     *
     * @param  array<int, int>  $andTags
     * @param  array<int, int>  $orTags
     * @param  array<int, int>  $notTags
     */
    public function withTagRules(array $andTags = [], array $orTags = [], array $notTags = []): static
    {
        return $this->state(fn (): array => [
            'query_options' => array_filter([
                'containsTagsAnd' => $andTags,
                'containsTagsOr' => $orTags,
                'containsTagsNot' => $notTags,
            ]),
        ]);
    }
}
