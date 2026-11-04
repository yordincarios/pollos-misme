<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        $today = today();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();
        $trendStart = $today->copy()->subDays(6)->startOfDay();
        $todaySales = Sale::query()->whereBetween('created_at', [$todayStart, $todayEnd]);

        $summary = (clone $todaySales)
            ->selectRaw(
                'COUNT(*) as tickets, COALESCE(SUM(total), 0) as total, COALESCE(SUM(CASE WHEN payment_method = ? THEN total ELSE 0 END), 0) as cash, COALESCE(SUM(CASE WHEN payment_method = ? THEN total ELSE 0 END), 0) as qr',
                ['cash', 'qr'],
            )
            ->first();

        $paymentBreakdown = Sale::query()
            ->whereBetween('created_at', [$trendStart, $todayEnd])
            ->select('payment_method')
            ->selectRaw('COUNT(*) as tickets, COALESCE(SUM(total), 0) as total')
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get()
            ->map(fn (Sale $sale): array => [
                'method' => $sale->payment_method,
                'tickets' => (int) $sale->tickets,
                'total' => (float) $sale->total,
            ]);

        $weekSummary = [
            'total' => (float) $paymentBreakdown->sum('total'),
            'tickets' => (int) $paymentBreakdown->sum('tickets'),
            'cash' => (float) ($paymentBreakdown->firstWhere('method', 'cash')['total'] ?? 0),
            'qr' => (float) ($paymentBreakdown->firstWhere('method', 'qr')['total'] ?? 0),
            'other' => (float) $paymentBreakdown
                ->whereNotIn('method', ['cash', 'qr'])
                ->sum('total'),
        ];

        $productSales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('dishes', 'dishes.id', '=', 'sale_items.dish_id')
            ->leftJoin('categories', 'categories.id', '=', 'dishes.category_id')
            ->whereBetween('sales.created_at', [$trendStart, $todayEnd])
            ->selectRaw("sale_items.dish_name as name, COALESCE(categories.name, 'Sin categoría') as category, SUM(sale_items.quantity) as quantity, COALESCE(SUM(sale_items.subtotal), 0) as total")
            ->groupBy('sale_items.dish_name', 'categories.name')
            ->orderByDesc('quantity')
            ->orderByDesc('total')
            ->get();

        $totalUnits = (int) $productSales->sum('quantity');
        $topProducts = $productSales
            ->take(5)
            ->map(fn ($product): array => [
                'name' => $product->name ?: 'Producto sin nombre',
                'category' => $product->category,
                'quantity' => (int) $product->quantity,
                'total' => (float) $product->total,
            ])
            ->values();

        $recentSales = Sale::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'ticket_number', 'total', 'payment_method', 'created_at']);

        $dailyTotals = Sale::query()
            ->whereBetween('created_at', [$trendStart, $todayEnd])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as tickets, COALESCE(SUM(total), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $previousTrendStart = $trendStart->copy()->subDays(7);
        $previousTrendEnd = $trendStart->copy()->subDay()->endOfDay();
        $previousDailyTotals = Sale::query()
            ->whereBetween('created_at', [$previousTrendStart, $previousTrendEnd])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as tickets, COALESCE(SUM(total), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $dailySales = [];
        $cumulative = 0.0;

        for ($day = $trendStart->copy(); $day->lte($today); $day->addDay()) {
            $key = $day->toDateString();
            $row = $dailyTotals->get($key);
            $previousRow = $previousDailyTotals->get($day->copy()->subDays(7)->toDateString());
            $total = (float) ($row->total ?? 0);
            $cumulative += $total;
            $dailySales[] = [
                'date' => $key,
                'label' => $day->locale('es')->isoFormat('ddd D'),
                'total' => $total,
                'tickets' => (int) ($row->tickets ?? 0),
                'cumulative' => $cumulative,
                'previous_total' => (float) ($previousRow->total ?? 0),
                'previous_tickets' => (int) ($previousRow->tickets ?? 0),
            ];
        }

        return Inertia::render('Administracion/PanelPrincipal', [
            'today' => $today->toDateString(),
            'period' => [
                'from' => $trendStart->toDateString(),
                'to' => $today->toDateString(),
            ],
            'summary' => [
                'total' => (float) $summary->total,
                'tickets' => (int) $summary->tickets,
                'cash' => (float) $summary->cash,
                'qr' => (float) $summary->qr,
            ],
            'weekSummary' => $weekSummary,
            'paymentBreakdown' => $paymentBreakdown,
            'productSales' => [
                'items' => $topProducts,
                'other_quantity' => max(0, $totalUnits - (int) $topProducts->sum('quantity')),
                'total_quantity' => $totalUnits,
            ],
            'recentSales' => $recentSales,
            'dailySales' => $dailySales,
        ]);
    }
}
