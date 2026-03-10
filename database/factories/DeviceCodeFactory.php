<?php

namespace Database\Factories;

use App\Models\DeviceCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceCode>
 */
class DeviceCodeFactory extends Factory
{
    protected $model = DeviceCode::class;

    public function definition(): array
    {
        return [
            'code' => DeviceCode::generateCode(),
            'client_id' => fake()->sha256(),
            'device_name' => fake()->words(2, true),
            'expires_at' => now()->addMinutes(5),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function authorized(): static
    {
        return $this->state(fn () => [
            'authorized_at' => now(),
        ]);
    }
}
