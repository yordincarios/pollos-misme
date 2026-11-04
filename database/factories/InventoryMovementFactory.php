<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);

        return [
            'dish_id' => Dish::factory(),
            'user_id' => User::factory(),
            'sale_id' => null,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => $quantity,
            'stock_after' => $quantity,
            'notes' => null,
        ];
    }
}
