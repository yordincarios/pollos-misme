<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/admin')->assertRedirectToRoute('login');
    }

    public function test_dashboard_summarizes_today_and_shows_seven_day_payment_distribution(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-10-28 12:00:00'));
        $category = Category::create(['name' => 'Platos fuertes']);
        $dish = Dish::create([
            'category_id' => $category->id,
            'name' => 'Silpancho',
            'price' => '12.50',
            'is_available' => true,
        ]);
        $drink = Dish::create([
            'category_id' => $category->id,
            'name' => 'Mocochinchi',
            'price' => '8.50',
            'is_available' => true,
        ]);

        $cashSale = $this->createSale('TKT-DASHBOARD-001', '25.00', 'cash');
        SaleItem::create([
            'sale_id' => $cashSale->id,
            'dish_id' => $dish->id,
            'dish_name' => $dish->name,
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-28 12:01:00'));
        $qrSale = $this->createSale('TKT-DASHBOARD-002', '8.50', 'qr');
        SaleItem::create([
            'sale_id' => $qrSale->id,
            'dish_id' => $drink->id,
            'dish_name' => $drink->name,
            'quantity' => 1,
            'price' => '8.50',
            'subtotal' => '8.50',
        ]);

        $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));
        $yesterdaySale = $this->createSale('TKT-DASHBOARD-003', '4.00', 'cash');
        SaleItem::create([
            'sale_id' => $yesterdaySale->id,
            'dish_id' => $drink->id,
            'dish_name' => $drink->name,
            'quantity' => 1,
            'price' => '4.00',
            'subtotal' => '4.00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->createSale('TKT-DASHBOARD-OLD', '100.00', 'cash');
        $this->travelTo(Carbon::parse('2026-10-28 15:00:00'));

        $this->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administracion/PanelPrincipal', shouldExist: false)
                ->where('today', '2026-10-28')
                ->where('summary.total', 33.5)
                ->where('summary.tickets', 2)
                ->where('summary.cash', 25)
                ->where('summary.qr', 8.5)
                ->where('weekSummary.total', 37.5)
                ->where('weekSummary.tickets', 3)
                ->where('weekSummary.cash', 29)
                ->where('weekSummary.qr', 8.5)
                ->has('paymentBreakdown', 2)
                ->where('paymentBreakdown.0.method', 'cash')
                ->where('paymentBreakdown.0.total', 29)
                ->where('paymentBreakdown.1.method', 'qr')
                ->where('paymentBreakdown.1.total', 8.5)
                ->where('productSales.total_quantity', 4)
                ->where('productSales.items.0.name', 'Silpancho')
                ->where('productSales.items.0.quantity', 2)
                ->where('productSales.items.0.category', 'Platos fuertes')
                ->where('productSales.items.1.name', 'Mocochinchi')
                ->where('productSales.items.1.quantity', 2)
                ->where('productSales.other_quantity', 0)
                ->where('dailySales.5.previous_total', 100)
                ->where('recentSales.0.ticket_number', 'TKT-DASHBOARD-002'));
    }

    private function createSale(string $ticketNumber, string $total, string $paymentMethod): Sale
    {
        return Sale::create([
            'ticket_number' => $ticketNumber,
            'total' => $total,
            'cash_received' => $paymentMethod === 'cash' ? $total : '0.00',
            'change_given' => '0.00',
            'payment_method' => $paymentMethod,
        ]);
    }
}
