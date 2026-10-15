<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'liveset_id' => Liveset::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'timestamp_seconds' => fake()->numberBetween(0, 3600),
        ];
    }

    public function reply(Comment $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'liveset_id' => $parent->liveset_id,
            'timestamp_seconds' => $parent->timestamp_seconds,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn () => [
            'deleted_at' => now(),
        ]);
    }
}
