<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_inventory_or_register_movements(): void
    {
        $dish = Dish::factory()->create();

        $this->get('/admin/inventory')->assertRedirectToRoute('login');
        $this->post("/admin/inventory/{$dish->id}/movements", [
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 5,
        ])->assertRedirectToRoute('login');
    }

    public function test_staff_can_enable_tracking_and_register_stock_movements(): void
    {
        $user = User::factory()->create();
        $dish = Dish::factory()->create(['is_available' => false]);
        $this->actingAs($user);

        $this->patch("/admin/inventory/{$dish->id}/tracking", [
            'track_stock' => true,
            'low_stock_threshold' => 2,
        ])->assertRedirect();

        $this->assertTrue($dish->fresh()->is_available);

        $this->post("/admin/inventory/{$dish->id}/movements", [
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 5,
        ])->assertRedirect();

        $this->post("/admin/inventory/{$dish->id}/movements", [
            'type' => InventoryMovement::TYPE_WASTE,
            'quantity' => 2,
        ])->assertRedirect();

        $this->post("/admin/inventory/{$dish->id}/movements", [
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'track_stock' => true,
            'stock_quantity' => 1,
            'low_stock_threshold' => 2,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'dish_id' => $dish->id,
            'user_id' => $user->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 5,
            'stock_after' => 5,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'dish_id' => $dish->id,
            'type' => InventoryMovement::TYPE_WASTE,
            'quantity' => -2,
            'stock_after' => 3,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'dish_id' => $dish->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => -2,
            'stock_after' => 1,
        ]);
    }

    public function test_staff_can_enable_tracking_with_initial_stock_in_one_step(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create(['stock_quantity' => 0]);

        $this->patch("/admin/inventory/{$dish->id}/tracking", [
            'track_stock' => true,
            'low_stock_threshold' => 2,
            'initial_stock' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'track_stock' => true,
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'dish_id' => $dish->id,
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 5,
            'stock_after' => 5,
            'notes' => 'Stock inicial',
        ]);
    }

    public function test_waste_cannot_exceed_available_stock(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->tracked(stock: 2)->create();

        $this->from('/admin/inventory')->post("/admin/inventory/{$dish->id}/movements", [
            'type' => InventoryMovement::TYPE_WASTE,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'stock_quantity' => 2]);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_inventory_page_shows_low_stock_summary(): void
    {
        $this->actingAs(User::factory()->create());
        Dish::factory()->tracked(stock: 2, threshold: 3)->create(['name' => 'Stock bajo']);
        Dish::factory()->tracked(stock: 0, threshold: 1)->create(['name' => 'Agotado']);
        Dish::factory()->create(['name' => 'Sin seguimiento']);

        $this->get('/admin/inventory')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administracion/Inventario/Inventario', shouldExist: false)
                ->where('summary.total', 3)
                ->where('summary.tracked', 2)
                ->where('summary.low', 1)
                ->where('summary.out', 1)
                ->has('dishes', 3));
    }
}
