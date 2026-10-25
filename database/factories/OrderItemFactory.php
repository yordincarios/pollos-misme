<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'dish_id' => Dish::factory(),
            'dish_name' => fake()->words(2, true),
            'quantity' => 1,
            'price' => '10.00',
            'subtotal' => '10.00',
            'notes' => null,
        ];
    }
}
