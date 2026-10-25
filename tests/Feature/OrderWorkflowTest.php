<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentSetting;
use App\Models\Sale;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_orders(): void
    {
        $this->get('/admin/orders')->assertRedirectToRoute('login');
    }

    public function test_staff_can_record_whatsapp_order_using_server_prices_and_item_snapshots(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create(['name' => 'Silpancho', 'price' => '12.50']);

        $this->post('/admin/orders', [
            'customer_name' => 'Ana Pérez',
            'customer_phone' => '71234567',
            'delivery_address' => 'Calle Aroma 123',
            'latitude' => '-17.3935000',
            'longitude' => '-66.1570000',
            'notes' => 'Llamar al llegar',
            'items' => [[
                'dish_id' => $dish->id,
                'quantity' => 2,
                'price' => '0.01',
            ]],
        ])->assertRedirectToRoute('admin.orders.index');

        $order = Order::query()->firstOrFail();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_name' => 'Ana Pérez',
            'status' => 'received',
            'total' => '25.00',
            'sale_id' => null,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'dish_name' => 'Silpancho',
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
        ]);
    }

    public function test_unavailable_dishes_cannot_be_added_to_an_order(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create(['is_available' => false]);

        $this->from('/admin/orders')->post('/admin/orders', [
            'customer_name' => 'Ana Pérez',
            'delivery_address' => 'Calle Aroma 123',
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_order_coordinates_must_be_provided_as_a_valid_pair(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create();

        $this->from('/admin/orders')->post('/admin/orders', [
            'customer_name' => 'Ana Pérez',
            'delivery_address' => 'Calle Aroma 123',
            'latitude' => '-17.3935',
            'items' => [['dish_id' => $dish->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('longitude');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_staff_can_advance_an_order_status(): void
    {
        $this->actingAs(User::factory()->create());
        $order = Order::factory()->create();

        $this->patch("/admin/orders/{$order->id}/status", [
            'status' => 'preparing',
        ])->assertRedirectToRoute('admin.orders.index');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
    }

    public function test_cash_payment_creates_one_sale_and_links_it_to_the_order(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create(['name' => 'Silpancho', 'price' => '12.50']);
        $order = $this->createOrderWithDish($dish);

        $payment = [
            'payment_method' => 'cash',
            'cash_received' => '30.00',
        ];
        $this->post("/admin/orders/{$order->id}/payment", $payment)
            ->assertRedirectToRoute('admin.orders.index');
        $this->post("/admin/orders/{$order->id}/payment", $payment)
            ->assertRedirectToRoute('admin.orders.index');

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'sale_id' => Sale::query()->value('id'),
        ]);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertDatabaseHas('sales', [
            'total' => '25.00',
            'cash_received' => '30.00',
            'change_given' => '5.00',
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'dish_id' => $dish->id,
            'dish_name' => 'Silpancho',
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
        ]);
        $this->assertDatabaseCount('sale_items', 1);

        $this->get('/admin/accounting')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 25)
                ->where('sales.data.0.ticket_number', Sale::query()->value('ticket_number')));
    }

    public function test_cash_payment_below_order_total_does_not_create_a_sale(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create();
        $order = $this->createOrderWithDish($dish);

        $this->from('/admin/orders')->post("/admin/orders/{$order->id}/payment", [
            'payment_method' => 'cash',
            'cash_received' => '20.00',
        ])->assertSessionHasErrors('cash_received');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'sale_id' => null]);
    }

    public function test_qr_order_payment_requires_manual_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configurePaymentQr();
        $dish = Dish::factory()->create();
        $order = $this->createOrderWithDish($dish);

        $this->from('/admin/orders')->post("/admin/orders/{$order->id}/payment", [
            'payment_method' => 'qr',
            'qr_confirmed' => false,
        ])->assertSessionHasErrors('qr_confirmed');

        $this->assertDatabaseCount('sales', 0);

        $this->post("/admin/orders/{$order->id}/payment", [
            'payment_method' => 'qr',
            'qr_confirmed' => true,
        ])->assertRedirectToRoute('admin.orders.index');

        $this->assertDatabaseHas('sales', [
            'payment_method' => 'qr',
            'total' => '25.00',
        ]);
    }

    public function test_qr_order_payment_is_rejected_without_a_configured_qr_image(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create();
        $order = $this->createOrderWithDish($dish);

        $this->from('/admin/orders')->post("/admin/orders/{$order->id}/payment", [
            'payment_method' => 'qr',
            'qr_confirmed' => true,
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'sale_id' => null]);
    }

    public function test_paid_orders_cannot_be_cancelled(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create();
        $order = $this->createOrderWithDish($dish);
        $sale = Sale::create([
            'ticket_number' => 'TKT-PAID-ORDER',
            'total' => '20.00',
            'cash_received' => '20.00',
            'change_given' => '0.00',
            'payment_method' => 'cash',
        ]);
        $order->sale()->associate($sale);
        $order->paid_at = now();
        $order->save();

        $this->from('/admin/orders')->patch("/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'received']);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create();
        $order = $this->createOrderWithDish($dish);
        $order->update(['status' => 'cancelled']);

        $this->from('/admin/orders')->post("/admin/orders/{$order->id}/payment", [
            'payment_method' => 'qr',
            'qr_confirmed' => true,
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'sale_id' => null]);
    }

    public function test_orders_page_maps_open_order_and_active_stall_locations(): void
    {
        $this->actingAs(User::factory()->create());
        $stall = Stall::factory()->create(['name' => 'Puesto Central']);
        $order = Order::factory()->create([
            'order_number' => 'ORD-MAP-001',
            'status' => 'preparing',
            'latitude' => '-17.4000000',
            'longitude' => '-66.1600000',
        ]);

        $this->get('/admin/orders')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Orders/Index', shouldExist: false)
                ->where('stalls.0.id', $stall->id)
                ->where('mapOrders.0.id', $order->id)
                ->where('mapOrders.0.order_number', 'ORD-MAP-001'));
    }

    private function createOrderWithDish(Dish $dish): Order
    {
        $order = Order::factory()->create(['total' => '25.00']);
        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'dish_name' => $dish->name,
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
        ]);

        return $order;
    }

    private function configurePaymentQr(): void
    {
        Storage::fake('public');
        $qrPath = UploadedFile::fake()->createWithContent(
            'merchant-qr.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=', true),
        )->store('payment-qr', 'public');

        PaymentSetting::create(['qr_image_path' => $qrPath]);
    }
}
