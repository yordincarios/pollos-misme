<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('7#######'),
            'delivery_address' => fake()->streetAddress(),
            'latitude' => -17.3935,
            'longitude' => -66.1570,
            'status' => 'received',
            'notes' => null,
            'total' => '0.00',
            'sale_id' => null,
            'paid_at' => null,
        ];
    }
}
