<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GastoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_expenses(): void
    {
        $this->get('/admin/gastos')->assertRedirectToRoute('login');
    }

    public function test_staff_can_register_and_delete_an_expense(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/admin/gastos', [
            'descripcion' => 'Compra de pollo',
            'categoria' => 'insumos',
            'monto' => '150.50',
            'fecha' => '2026-11-10',
            'metodo_pago' => 'efectivo',
            'notas' => 'Proveedor central',
        ])->assertRedirect();

        $gasto = Gasto::query()->firstOrFail();
        $this->assertSame('150.50', $gasto->monto);

        $this->get('/admin/gastos')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administracion/Gastos', shouldExist: false)
                ->where('resumen.total', 150.5)
                ->has('gastos.data', 1));

        $this->delete("/admin/gastos/{$gasto->id}")->assertRedirect();
        $this->assertDatabaseMissing('gastos', ['id' => $gasto->id]);
    }

    public function test_dashboard_shows_income_expenses_and_balance(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));

        Sale::create([
            'ticket_number' => 'TKT-GASTO-001',
            'total' => '100.00',
            'cash_received' => '0.00',
            'change_given' => '0.00',
            'payment_method' => 'cash',
        ]);
        Gasto::create([
            'descripcion' => 'Servicio de internet',
            'categoria' => 'servicios',
            'monto' => '30.00',
            'fecha' => '2026-11-10',
            'metodo_pago' => 'transferencia',
            'user_id' => auth()->id(),
        ]);

        $this->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 100)
                ->where('finanzas.gastos_hoy', 30)
                ->where('finanzas.balance_hoy', 70));
    }
}
