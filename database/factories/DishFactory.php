<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'price' => '10.00',
            'image' => null,
            'is_available' => true,
            'track_stock' => false,
            'stock_quantity' => 0,
            'low_stock_threshold' => 0,
        ];
    }

    public function tracked(int $stock = 10, int $threshold = 3): static
    {
        return $this->state(fn (): array => [
            'track_stock' => true,
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
        ]);
    }
}
