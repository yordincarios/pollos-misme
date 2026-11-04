<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use App\Models\InventoryMovement;
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

        return Inertia::render('PuntoDeVenta/PuntoDeVenta', [
            'categories' => $categories,
            'dishes' => $dishes,
            'archivedDishes' => $archivedDishes,
        ]);
    }

    public function storeSale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.dish_id' => ['required', 'integer', 'distinct', 'exists:dishes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'qr'])],
            'qr_confirmed' => ['exclude_unless:payment_method,qr', 'required', 'accepted'],
        ]);

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

            $requestedQuantities = collect($validated['items'])
                ->groupBy('dish_id')
                ->map(fn ($items): int => (int) $items->sum('quantity'));

            $insufficientStock = $dishes->filter(
                fn (Dish $dish): bool => $dish->track_stock
                    && $dish->stock_quantity < $requestedQuantities->get($dish->id, 0),
            );

            if ($insufficientStock->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Stock insuficiente: '.$insufficientStock
                        ->map(fn (Dish $dish): string => "{$dish->name} ({$dish->stock_quantity} disponibles)")
                        ->implode(', ').'.',
                ]);
            }

            $totalCents = 0;
            $saleItems = [];

            foreach ($validated['items'] as $item) {
                $dish = $dishes->get($item['dish_id']);

                if (! $dish->track_stock && ! $dish->is_available) {
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

            $sale = Sale::create([
                'ticket_number' => 'TKT-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'cash_received' => '0.00',
                'change_given' => '0.00',
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($saleItems as $saleItem) {
                $sale->items()->create($saleItem);
            }

            foreach ($dishes as $dish) {
                $quantity = (int) $requestedQuantities->get($dish->id, 0);

                if (! $dish->track_stock || $quantity === 0) {
                    continue;
                }

                $stockAfter = $dish->stock_quantity - $quantity;
                $dish->update(['stock_quantity' => $stockAfter]);

                InventoryMovement::create([
                    'dish_id' => $dish->id,
                    'user_id' => auth()->id(),
                    'sale_id' => $sale->id,
                    'type' => InventoryMovement::TYPE_SALE,
                    'quantity' => -$quantity,
                    'stock_after' => $stockAfter,
                    'notes' => null,
                ]);
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
