<?php

namespace Database\Factories;

use App\Services\TrackLinker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Track>
 */
class TrackFactory extends Factory
{
    public function definition(): array
    {
        $artist = fake()->name();
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'artist' => $artist,
            'title' => $title,
            'version' => null,
            'slug' => Str::slug("{$artist} {$title}").'-'.fake()->unique()->numberBetween(1, 999999),
            'match_key' => fn (array $attributes) => app(TrackLinker::class)->matchKey($attributes['artist'], $attributes['title'], $attributes['version']),
        ];
    }
}
