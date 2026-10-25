<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use App\Models\PaymentSetting;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('dishes')
            ->withCount(['dishes as archived_dishes_count' => fn (Builder $query) => $query->onlyTrashed()])
            ->orderBy('name')
            ->get();
        $dishes = Dish::with('category')->orderBy('name')->get();
        $archivedDishes = Dish::onlyTrashed()->with('category')->orderBy('name')->get();

        return Inertia::render('Pos/Index', [
            'categories' => $categories,
            'dishes' => $dishes,
            'archivedDishes' => $archivedDishes,
            'paymentQrUrl' => PaymentSetting::qrImageUrl(),
        ]);
    }

    public function storeSale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.dish_id' => ['required', 'exists:dishes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'cash_received' => ['required_if:payment_method,cash', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'qr'])],
            'qr_confirmed' => ['exclude_unless:payment_method,qr', 'required', 'accepted'],
        ]);

        if ($validated['payment_method'] === 'qr' && PaymentSetting::qrImagePath() === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Configura el QR de pago desde Ajustes antes de registrar cobros QR.',
            ]);
        }

        $sale = DB::transaction(function () use ($validated): Sale {
            $dishIds = collect($validated['items'])
                ->pluck('dish_id')
                ->unique()
                ->values();
            $dishes = Dish::query()
                ->whereIn('id', $dishIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($dishes->count() !== $dishIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o más platillos ya no están disponibles.',
                ]);
            }

            $totalCents = 0;
            $saleItems = [];

            foreach ($validated['items'] as $item) {
                $dish = $dishes->get($item['dish_id']);

                if (! $dish->is_available) {
                    throw ValidationException::withMessages([
                        'items' => "El platillo {$dish->name} no está disponible.",
                    ]);
                }

                $priceCents = (int) round((float) $dish->price * 100);
                $subtotalCents = $priceCents * $item['quantity'];
                $totalCents += $subtotalCents;
                $saleItems[] = [
                    'dish_id' => $dish->id,
                    'dish_name' => $dish->name,
                    'quantity' => $item['quantity'],
                    'price' => number_format($priceCents / 100, 2, '.', ''),
                    'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                    'notes' => $item['notes'] ?? null,
                ];
            }

            if ($totalCents > 9_999_999_999) {
                throw ValidationException::withMessages([
                    'items' => 'El total de la venta supera el límite permitido.',
                ]);
            }

            $cashReceivedCents = $validated['payment_method'] === 'cash'
                ? (int) round((float) $validated['cash_received'] * 100)
                : 0;

            if ($validated['payment_method'] === 'cash' && $cashReceivedCents < $totalCents) {
                throw ValidationException::withMessages([
                    'cash_received' => 'El efectivo recibido debe ser mayor o igual al total.',
                ]);
            }

            $sale = Sale::create([
                'ticket_number' => 'TKT-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'cash_received' => number_format($cashReceivedCents / 100, 2, '.', ''),
                'change_given' => number_format(max(0, $cashReceivedCents - $totalCents) / 100, 2, '.', ''),
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($saleItems as $saleItem) {
                $sale->items()->create($saleItem);
            }

            return $sale;
        });

        $sale->load('items.dish');

        return redirect()->back()->with([
            'success' => 'Venta registrada con éxito.',
            'lastSale' => $sale,
        ]);
    }
}
