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

class AccountingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_accounting_report(): void
    {
        $this->get('/admin/accounting')->assertRedirectToRoute('login');
        $this->get('/admin/accounting/print')->assertRedirectToRoute('login');
    }

    public function test_authenticated_staff_can_filter_sales_and_cash_by_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));

        $this->createSale('TKT-ACCOUNT-001', '20.50', 'cash');
        $this->createSale('TKT-ACCOUNT-002', '14.25', 'qr');
        $this->createSale('TKT-ACCOUNT-003', '5.00', 'card');
        $this->travelTo(Carbon::parse('2026-10-17 12:00:00'));
        $this->createSale('TKT-ACCOUNT-004', '100.00', 'cash');

        $this->get('/admin/accounting?from=2026-10-18&to=2026-10-18')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 39.75)
                ->where('summary.tickets', 3)
                ->where('summary.cash', 20.5)
                ->where('summary.qr', 14.25)
                ->where('summary.other', 5)
                ->has('paymentBreakdown', 3)
                ->where('sales.total', 3));
    }

    public function test_accounting_report_includes_item_names_and_line_totals(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));
        $sale = $this->createSale('TKT-DETAIL-001', '25.00', 'cash');
        $dish = $this->createDish('Silpancho');
        SaleItem::create([
            'sale_id' => $sale->id,
            'dish_id' => $dish->id,
            'dish_name' => 'Silpancho',
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
            'notes' => 'Sin picante',
        ]);

        $this->get('/admin/accounting?from=2026-10-18&to=2026-10-18')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sales.data.0.items.0.dish_name', 'Silpancho')
                ->where('sales.data.0.items.0.quantity', 2)
                ->where('sales.data.0.items.0.subtotal', '25.00')
                ->where('sales.data.0.items.0.notes', 'Sin picante'));
    }

    public function test_print_report_includes_all_sales_and_their_item_details_for_selected_period(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));

        for ($saleNumber = 1; $saleNumber <= 20; $saleNumber++) {
            $this->createSale('TKT-PRINT-'.str_pad((string) $saleNumber, 3, '0', STR_PAD_LEFT), '1.00', 'cash');
        }

        $this->travel(1)->seconds();
        $sale = $this->createSale('TKT-PRINT-DETAIL', '25.00', 'qr');
        $dish = $this->createDish('Silpancho');
        SaleItem::create([
            'sale_id' => $sale->id,
            'dish_id' => $dish->id,
            'dish_name' => 'Silpancho',
            'quantity' => 2,
            'price' => '12.50',
            'subtotal' => '25.00',
        ]);

        $this->get('/admin/accounting/print?from=2026-10-18&to=2026-10-18')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/AccountingPrint', shouldExist: false)
                ->where('filters.from', '2026-10-18')
                ->where('summary.tickets', 21)
                ->where('sales.0.ticket_number', 'TKT-PRINT-DETAIL')
                ->where('sales.0.items.0.dish_name', 'Silpancho')
                ->has('sales', 21));
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

    private function createDish(string $name): Dish
    {
        $category = Category::create(['name' => 'Platos fuertes']);

        return Dish::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => '12.50',
            'is_available' => true,
        ]);
    }
}
