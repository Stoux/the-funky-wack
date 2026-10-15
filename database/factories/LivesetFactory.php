<?php

namespace Database\Factories;

use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Liveset>
 */
class LivesetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'title' => fake()->words(3, true),
            'artist_name' => fake()->name(),
            'duration_in_seconds' => fake()->numberBetween(1800, 7200),
        ];
    }
}
