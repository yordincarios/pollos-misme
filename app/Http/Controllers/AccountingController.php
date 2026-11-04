<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);
        $paymentMethod = $this->paymentMethod($request);
        $sales = $this->salesForPeriod($from, $to, $paymentMethod);

        $paymentBreakdown = (clone $sales)
            ->select('payment_method', DB::raw('COUNT(*) as tickets'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get()
            ->map(fn (Sale $sale): array => [
                'method' => $sale->payment_method,
                'tickets' => (int) $sale->tickets,
                'total' => (float) $sale->total,
            ]);

        return Inertia::render('Administracion/Contabilidad/Contabilidad', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'payment_method' => $paymentMethod,
            ],
            'today' => today()->toDateString(),
            'summary' => $this->summaryFor($sales),
            'paymentBreakdown' => $paymentBreakdown,
            'topDishes' => $this->topDishes($sales),
            'paymentMethods' => [
                ['value' => '', 'label' => 'Todos los métodos'],
                ['value' => 'cash', 'label' => 'Efectivo'],
                ['value' => 'qr', 'label' => 'QR'],
            ],
            'sales' => (clone $sales)
                ->with(['items.dish'])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function printReport(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);
        $paymentMethod = $this->paymentMethod($request);
        $sales = $this->salesForPeriod($from, $to, $paymentMethod);

        return Inertia::render('Administracion/Contabilidad/ImprimirReporte', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'payment_method' => $paymentMethod,
            ],
            'summary' => $this->summaryFor($sales),
            'sales' => (clone $sales)
                ->withCount('items')
                ->withSum('items', 'quantity')
                ->latest()
                ->get(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $sales = $this->salesForPeriod($from, $to, $this->paymentMethod($request))
            ->with(['items.dish'])
            ->latest()
            ->get();

        $filename = 'ventas_'.$from->toDateString().'_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($sales): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Ticket', 'Fecha', 'Método', 'Plato', 'Cantidad', 'Precio unitario', 'Subtotal', 'Total venta']);

            foreach ($sales as $sale) {
                if ($sale->items->isEmpty()) {
                    fputcsv($handle, [
                        $sale->ticket_number,
                        $sale->created_at->toDateTimeString(),
                        $sale->payment_method,
                        'Sin detalle',
                        0,
                        0,
                        0,
                        $sale->total,
                    ]);

                    continue;
                }

                foreach ($sale->items as $item) {
                    fputcsv($handle, [
                        $sale->ticket_number,
                        $sale->created_at->toDateTimeString(),
                        $sale->payment_method,
                        $item->dish_name ?: $item->dish?->name ?: 'Plato no disponible',
                        $item->quantity,
                        $item->price,
                        $item->subtotal,
                        $sale->total,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_method' => ['nullable', 'string', 'in:cash,qr,card,transfer'],
        ]);

        $fromDate = $validated['from'] ?? $validated['to'] ?? today()->toDateString();
        $toDate = $validated['to'] ?? $validated['from'] ?? today()->toDateString();

        return [
            Carbon::parse($fromDate)->startOfDay(),
            Carbon::parse($toDate)->endOfDay(),
        ];
    }

    private function paymentMethod(Request $request): string
    {
        $method = (string) $request->query('payment_method', '');

        return in_array($method, ['cash', 'qr', 'card', 'transfer'], true) ? $method : '';
    }

    private function salesForPeriod(Carbon $from, Carbon $to, string $paymentMethod = ''): Builder
    {
        return Sale::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($paymentMethod !== '', fn (Builder $query): Builder => $query->where('payment_method', $paymentMethod));
    }

    /**
     * @return array{total: float, tickets: int, cash: float, qr: float, other: float, average: float, items: int}
     */
    private function summaryFor(Builder $sales): array
    {
        $total = (float) (clone $sales)->sum('total');
        $tickets = (clone $sales)->count();

        return [
            'total' => $total,
            'tickets' => $tickets,
            'cash' => (float) (clone $sales)->where('payment_method', 'cash')->sum('total'),
            'qr' => (float) (clone $sales)->where('payment_method', 'qr')->sum('total'),
            'other' => (float) (clone $sales)->whereNotIn('payment_method', ['cash', 'qr'])->sum('total'),
            'average' => $tickets > 0 ? $total / $tickets : 0.0,
            'items' => (int) DB::table('sale_items')->whereIn('sale_id', (clone $sales)->select('id'))->sum('quantity'),
        ];
    }

    /**
     * @return array<int, array{name: string, quantity: int, total: float}>
     */
    private function topDishes(Builder $sales): array
    {
        return DB::table('sale_items')
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('COALESCE(dish_name, "Plato no disponible") as name, SUM(quantity) as quantity, SUM(subtotal) as total')
            ->groupBy('dish_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($item): array => [
                'name' => (string) $item->name,
                'quantity' => (int) $item->quantity,
                'total' => (float) $item->total,
            ])
            ->all();
    }
}
