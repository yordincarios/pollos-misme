<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_uses_the_server_price_without_cash_change_tracking(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish(price: '10.00');

        $response = $this->from('/pos')->post('/pos/sales', [
            'items' => [[
                'dish_id' => $dish->id,
                'quantity' => 2,
                'price' => '0.01',
            ]],
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect('/pos');
        $this->assertDatabaseHas('sales', [
            'total' => '20.00',
            'cash_received' => '0.00',
            'change_given' => '0.00',
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'dish_id' => $dish->id,
            'dish_name' => 'Plato de prueba',
            'quantity' => 2,
            'price' => '10.00',
            'subtotal' => '20.00',
        ]);
    }

    public function test_unavailable_dishes_cannot_be_sold(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish(isAvailable: false);

        $response = $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'cash_received' => '25.00',
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_stock_controls_availability_for_tracked_dishes(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish(isAvailable: false);
        $dish->update([
            'track_stock' => true,
            'stock_quantity' => 1,
            'low_stock_threshold' => 0,
        ]);

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'cash_received' => '10.00',
            'payment_method' => 'cash',
        ])->assertRedirect('/pos');

        $this->assertDatabaseHas('sales', ['total' => '10.00']);
        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'stock_quantity' => 0]);
    }

    public function test_tracked_dishes_cannot_toggle_manual_availability(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();
        $dish->update([
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $this->from('/admin/catalog')
            ->patch("/dishes/{$dish->id}/toggle")
            ->assertSessionHasErrors('availability');

        $this->assertTrue($dish->fresh()->is_available);
    }

    public function test_card_payments_are_no_longer_accepted(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_qr_sales_require_a_manual_payment_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'payment_method' => 'qr',
        ])->assertSessionHasErrors('qr_confirmed');

        $this->assertDatabaseCount('sales', 0);

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'payment_method' => 'qr',
            'qr_confirmed' => true,
        ])->assertRedirect('/pos');

        $this->assertDatabaseHas('sales', [
            'total' => '10.00',
            'payment_method' => 'qr',
            'cash_received' => '0.00',
            'change_given' => '0.00',
        ]);
    }

    public function test_qr_sales_can_be_recorded_without_a_configured_qr_image(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
            'payment_method' => 'qr',
            'qr_confirmed' => true,
        ])->assertRedirect('/pos');

        $this->assertDatabaseHas('sales', [
            'payment_method' => 'qr',
            'total' => '10.00',
        ]);
    }

    public function test_tracked_stock_is_deducted_and_sale_movement_is_recorded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $dish = $this->createDish();
        $dish->update([
            'track_stock' => true,
            'stock_quantity' => 5,
            'low_stock_threshold' => 1,
        ]);

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 2]],
            'cash_received' => '20.00',
            'payment_method' => 'cash',
        ])->assertRedirect('/pos');

        $sale = Sale::query()->firstOrFail();
        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('inventory_movements', [
            'dish_id' => $dish->id,
            'user_id' => $user->id,
            'sale_id' => $sale->id,
            'type' => InventoryMovement::TYPE_SALE,
            'quantity' => -2,
            'stock_after' => 3,
        ]);
    }

    public function test_sale_is_rejected_when_tracked_stock_is_insufficient(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();
        $dish->update([
            'track_stock' => true,
            'stock_quantity' => 1,
            'low_stock_threshold' => 0,
        ]);

        $this->from('/pos')->post('/pos/sales', [
            'items' => [['dish_id' => $dish->id, 'quantity' => 2]],
            'cash_received' => '20.00',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'stock_quantity' => 1]);
    }

    public function test_archiving_a_dish_preserves_its_previous_sale_items(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();
        $sale = Sale::create([
            'ticket_number' => 'TKT-TEST-'.fake()->unique()->numerify('####'),
            'total' => '10.00',
            'cash_received' => '10.00',
            'change_given' => '0.00',
            'payment_method' => 'cash',
        ]);
        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'dish_id' => $dish->id,
            'quantity' => 1,
            'price' => '10.00',
            'subtotal' => '10.00',
        ]);

        $this->delete("/dishes/{$dish->id}")->assertRedirect();

        $this->assertSoftDeleted('dishes', ['id' => $dish->id]);
        $this->assertDatabaseHas('sale_items', ['id' => $saleItem->id, 'dish_id' => $dish->id]);
        $this->assertSame($dish->id, $saleItem->fresh()->dish->id);
    }

    public function test_categories_with_dishes_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();

        $this->from('/pos')->delete("/categories/{$dish->category_id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $dish->category_id]);
    }

    public function test_staff_can_create_and_rename_categories(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/categories', ['name' => 'Bebidas'])->assertRedirect();
        $category = Category::where('name', 'Bebidas')->firstOrFail();
        $this->patch("/categories/{$category->id}", ['name' => 'Bebidas frías'])->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Bebidas frías']);
    }

    public function test_catalog_includes_active_and_archived_dish_counts_for_categories(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Platos fuertes']);
        $this->createDishForCategory($category, 'Plato activo')->save();
        $archivedDish = $this->createDishForCategory($category, 'Plato retirado');
        $archivedDish->delete();

        $this->get('/pos')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.dishes_count', 1)
            ->where('categories.0.archived_dishes_count', 1));
    }

    public function test_staff_can_remove_an_existing_dish_image(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('public');
        $dish = $this->createDish();
        $imagePath = 'dishes/existing-image.jpg';
        Storage::disk('public')->put($imagePath, 'previous image');
        $dish->update(['image' => $imagePath]);

        $this->post("/dishes/{$dish->id}", [
            'name' => $dish->name,
            'price' => $dish->price,
            'category_id' => $dish->category_id,
            'is_available' => true,
            'remove_image' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'image' => null]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_archived_dishes_can_be_restored(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = $this->createDish();
        $dish->delete();

        $this->patch("/dishes/{$dish->id}/restore")->assertRedirect();

        $this->assertNotSoftDeleted('dishes', ['id' => $dish->id]);
    }

    private function createDish(string $price = '10.00', bool $isAvailable = true): Dish
    {
        $category = Category::create(['name' => 'Platos fuertes']);

        return Dish::create([
            'category_id' => $category->id,
            'name' => 'Plato de prueba',
            'price' => $price,
            'is_available' => $isAvailable,
        ]);
    }

    private function createDishForCategory(Category $category, string $name): Dish
    {
        return Dish::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => '10.00',
            'is_available' => true,
        ]);
    }
}
