<?php

namespace Database\Factories;

use App\Models\Stall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stall>
 */
class StallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'address' => fake()->address(),
            'latitude' => -17.3935,
            'longitude' => -66.1570,
            'is_active' => true,
        ];
    }
}
