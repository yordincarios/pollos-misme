<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Models\Sale;
use App\Models\Stall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
        ]);

        $orders = Order::query()
            ->with(['items', 'sale'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $mapOrders = Order::query()
            ->whereIn('status', ['received', 'preparing', 'ready'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('created_at')
            ->get(['id', 'order_number', 'customer_name', 'delivery_address', 'latitude', 'longitude', 'status', 'total'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'delivery_address' => $order->delivery_address,
                'latitude' => (float) $order->latitude,
                'longitude' => (float) $order->longitude,
                'status' => $order->status,
                'total' => (float) $order->total,
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'filters' => ['status' => $validated['status'] ?? ''],
            'paymentQrUrl' => PaymentSetting::qrImageUrl(),
            'availableDishes' => Dish::query()
                ->where('is_available', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price']),
            'stalls' => Stall::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'latitude', 'longitude']),
            'mapOrders' => $mapOrders,
            'summary' => [
                'open' => Order::query()->whereIn('status', ['received', 'preparing', 'ready'])->count(),
                'awaitingPayment' => Order::query()->whereNull('sale_id')->where('status', '!=', 'cancelled')->count(),
                'paid' => Order::query()->whereNotNull('sale_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.dish_id' => ['required', 'integer', 'distinct', 'exists:dishes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated): void {
            $dishIds = collect($validated['items'])->pluck('dish_id')->unique()->values();
            $dishes = Dish::query()
                ->whereIn('id', $dishIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($dishes->count() !== $dishIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Uno o más platos ya no existen o fueron retirados.',
                ]);
            }

            $totalCents = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $dish = $dishes->get($item['dish_id']);

                if (! $dish->is_available) {
                    throw ValidationException::withMessages([
                        'items' => "El plato {$dish->name} no está disponible.",
                    ]);
                }

                $priceCents = (int) round((float) $dish->price * 100);
                $subtotalCents = $priceCents * $item['quantity'];
                $totalCents += $subtotalCents;
                $orderItems[] = [
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
                    'items' => 'El total del pedido supera el límite permitido.',
                ]);
            }

            $order = Order::create([
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'delivery_address' => $validated['delivery_address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
                'total' => number_format($totalCents / 100, 2, '.', ''),
            ]);

            foreach ($orderItems as $orderItem) {
                $order->items()->create($orderItem);
            }
        });

        return redirect()->route('admin.orders.index')->with('success', 'Pedido registrado.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        DB::transaction(function () use ($order, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->sale_id !== null && $validated['status'] === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Un pedido cobrado no se puede cancelar desde aquí.',
                ]);
            }

            $lockedOrder->update(['status' => $validated['status']]);
        });

        return redirect()->route('admin.orders.index')->with('success', 'Estado del pedido actualizado.');
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(['cash', 'qr'])],
            'cash_received' => ['required_if:payment_method,cash', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'qr_confirmed' => ['exclude_unless:payment_method,qr', 'required', 'accepted'],
        ]);

        if ($validated['payment_method'] === 'qr' && PaymentSetting::qrImagePath() === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Configura el QR de pago antes de cobrar pedidos con QR.',
            ]);
        }

        /** @var array{sale: Sale, alreadyPaid: bool} $result */
        $result = DB::transaction(function () use ($order, $validated): array {
            $lockedOrder = Order::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->sale_id !== null) {
                return [
                    'sale' => $lockedOrder->sale()->firstOrFail(),
                    'alreadyPaid' => true,
                ];
            }

            if ($lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment_method' => 'No se puede cobrar un pedido cancelado.',
                ]);
            }

            $totalCents = (int) round((float) $lockedOrder->total * 100);
            $cashReceivedCents = $validated['payment_method'] === 'cash'
                ? (int) round((float) $validated['cash_received'] * 100)
                : 0;

            if ($validated['payment_method'] === 'cash' && $cashReceivedCents < $totalCents) {
                throw ValidationException::withMessages([
                    'cash_received' => 'El efectivo recibido debe ser mayor o igual al total del pedido.',
                ]);
            }

            $sale = Sale::create([
                'ticket_number' => 'TKT-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'cash_received' => number_format($cashReceivedCents / 100, 2, '.', ''),
                'change_given' => number_format(max(0, $cashReceivedCents - $totalCents) / 100, 2, '.', ''),
                'payment_method' => $validated['payment_method'],
            ]);

            foreach ($lockedOrder->items as $orderItem) {
                $sale->items()->create([
                    'dish_id' => $orderItem->dish_id,
                    'dish_name' => $orderItem->dish_name,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'subtotal' => $orderItem->subtotal,
                    'notes' => $orderItem->notes,
                ]);
            }

            $lockedOrder->sale()->associate($sale);
            $lockedOrder->paid_at = now();
            $lockedOrder->save();

            return [
                'sale' => $sale,
                'alreadyPaid' => false,
            ];
        }, attempts: 3);

        $message = $result['alreadyPaid']
            ? 'Este pedido ya estaba cobrado; no se generó otra venta.'
            : 'Pedido cobrado y registrado en contabilidad.';

        return redirect()->route('admin.orders.index')->with('success', $message);
    }
}
