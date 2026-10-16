<?php

namespace Database\Factories;

use App\Enums\TrackAliasStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrackAlias>
 */
class TrackAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'text' => fake()->name().' - '.fake()->unique()->words(3, true),
            'status' => TrackAliasStatus::Auto,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(['status' => TrackAliasStatus::Reviewed]);
    }
}
