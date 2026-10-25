<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);
        $sales = $this->salesForPeriod($from, $to);

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

        return Inertia::render('Admin/Accounting', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'today' => today()->toDateString(),
            'summary' => $this->summaryFor($sales),
            'paymentBreakdown' => $paymentBreakdown,
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
        $sales = $this->salesForPeriod($from, $to);

        return Inertia::render('Admin/AccountingPrint', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $this->summaryFor($sales),
            'sales' => (clone $sales)
                ->with(['items.dish'])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $fromDate = $validated['from'] ?? $validated['to'] ?? today()->toDateString();
        $toDate = $validated['to'] ?? $validated['from'] ?? today()->toDateString();

        return [
            Carbon::parse($fromDate)->startOfDay(),
            Carbon::parse($toDate)->endOfDay(),
        ];
    }

    private function salesForPeriod(Carbon $from, Carbon $to): Builder
    {
        return Sale::query()->whereBetween('created_at', [$from, $to]);
    }

    /**
     * @return array{total: float, tickets: int, cash: float, qr: float, other: float}
     */
    private function summaryFor(Builder $sales): array
    {
        return [
            'total' => (float) (clone $sales)->sum('total'),
            'tickets' => (clone $sales)->count(),
            'cash' => (float) (clone $sales)->where('payment_method', 'cash')->sum('total'),
            'qr' => (float) (clone $sales)->where('payment_method', 'qr')->sum('total'),
            'other' => (float) (clone $sales)->whereNotIn('payment_method', ['cash', 'qr'])->sum('total'),
        ];
    }
}
