<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::in(['tracked', 'low', 'out', 'untracked'])],
        ]);

        $dishes = Dish::query()
            ->with('category')
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($validated['category'] ?? null, fn (Builder $query, int $category) => $query->where('category_id', $category))
            ->when($validated['status'] ?? null, function (Builder $query, string $status): void {
                match ($status) {
                    'tracked' => $query->where('track_stock', true),
                    'low' => $query->where('track_stock', true)
                        ->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
                    'out' => $query->where('track_stock', true)->where('stock_quantity', 0),
                    'untracked' => $query->where('track_stock', false),
                };
            })
            ->orderByRaw('CASE
                WHEN track_stock = 1 AND stock_quantity = 0 THEN 0
                WHEN track_stock = 1 AND stock_quantity <= low_stock_threshold THEN 1
                WHEN track_stock = 0 THEN 3
                ELSE 2
            END')
            ->orderBy('name')
            ->get();

        return Inertia::render('Administracion/Inventario/Inventario', [
            'dishes' => $dishes,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'category' => $validated['category'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
            'summary' => [
                'total' => Dish::query()->count(),
                'tracked' => Dish::query()->where('track_stock', true)->count(),
                'low' => Dish::query()
                    ->where('track_stock', true)
                    ->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->count(),
                'out' => Dish::query()->where('track_stock', true)->where('stock_quantity', 0)->count(),
            ],
            'movements' => InventoryMovement::query()
                ->with(['dish', 'user', 'sale'])
                ->latest()
                ->limit(50)
                ->get(),
            'movementTypes' => [
                InventoryMovement::TYPE_ENTRY,
                InventoryMovement::TYPE_WASTE,
                InventoryMovement::TYPE_ADJUSTMENT,
            ],
        ]);
    }

    public function updateTracking(Request $request, Dish $dish): RedirectResponse
    {
        $validated = $request->validate([
            'track_stock' => ['required', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        DB::transaction(function () use ($dish, $validated): void {
            $lockedDish = Dish::query()->lockForUpdate()->findOrFail($dish->id);
            $wasTracking = $lockedDish->track_stock;
            $initialStock = (int) ($validated['initial_stock'] ?? 0);

            $lockedDish->fill([
                'track_stock' => $validated['track_stock'],
                'low_stock_threshold' => $validated['low_stock_threshold'],
            ]);

            if ($lockedDish->track_stock) {
                $lockedDish->is_available = true;
            }

            if (! $wasTracking && $lockedDish->track_stock && $initialStock > 0) {
                $stockAfter = $lockedDish->stock_quantity + $initialStock;
                $lockedDish->stock_quantity = $stockAfter;
                $lockedDish->save();

                InventoryMovement::create([
                    'dish_id' => $lockedDish->id,
                    'user_id' => auth()->id(),
                    'sale_id' => null,
                    'type' => InventoryMovement::TYPE_ENTRY,
                    'quantity' => $initialStock,
                    'stock_after' => $stockAfter,
                    'notes' => 'Stock inicial',
                ]);

                return;
            }

            $lockedDish->save();
        });

        return back()->with('success', 'Configuración de inventario actualizada.');
    }

    public function storeMovement(Request $request, Dish $dish): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([
                InventoryMovement::TYPE_ENTRY,
                InventoryMovement::TYPE_WASTE,
                InventoryMovement::TYPE_ADJUSTMENT,
            ])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($dish, $validated): void {
            $lockedDish = Dish::query()->lockForUpdate()->findOrFail($dish->id);

            if (! $lockedDish->track_stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Activa el control de inventario para este producto.',
                ]);
            }

            $stockBefore = $lockedDish->stock_quantity;
            $type = $validated['type'];
            $quantity = $validated['quantity'];

            [$stockAfter, $delta] = match ($type) {
                InventoryMovement::TYPE_ENTRY => $this->entryMovement($stockBefore, $quantity),
                InventoryMovement::TYPE_WASTE => $this->wasteMovement($stockBefore, $quantity),
                InventoryMovement::TYPE_ADJUSTMENT => [$quantity, $quantity - $stockBefore],
            };

            if ($delta === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'El movimiento no modifica el stock actual.',
                ]);
            }

            $lockedDish->update(['stock_quantity' => $stockAfter]);

            InventoryMovement::create([
                'dish_id' => $lockedDish->id,
                'user_id' => auth()->id(),
                'sale_id' => null,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => $stockAfter,
                'notes' => $validated['notes'] ?? null,
            ]);
        }, attempts: 3);

        return back()->with('success', 'Movimiento de inventario registrado.');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function entryMovement(int $stock, int $quantity): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La entrada debe ser mayor que cero.',
            ]);
        }

        return [$stock + $quantity, $quantity];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function wasteMovement(int $stock, int $quantity): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La merma debe ser mayor que cero.',
            ]);
        }

        if ($quantity > $stock) {
            throw ValidationException::withMessages([
                'quantity' => 'La merma no puede superar el stock disponible.',
            ]);
        }

        return [$stock - $quantity, -$quantity];
    }
}
