<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GastoController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'categoria' => ['nullable', Rule::in(Gasto::CATEGORIAS)],
        ]);

        $desde = Carbon::parse($validated['desde'] ?? today()->startOfMonth()->toDateString())->startOfDay();
        $hasta = Carbon::parse($validated['hasta'] ?? today()->toDateString())->endOfDay();

        $query = Gasto::query()
            ->with('user')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($validated['categoria'] ?? null, fn ($query, string $categoria) => $query->where('categoria', $categoria))
            ->latest('fecha')
            ->latest('id');

        return Inertia::render('Administracion/Gastos', [
            'gastos' => (clone $query)->paginate(20)->withQueryString(),
            'filtros' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'categoria' => $validated['categoria'] ?? '',
            ],
            'resumen' => [
                'total' => (float) (clone $query)->sum('monto'),
                'cantidad' => (clone $query)->count(),
            ],
            'categorias' => Gasto::CATEGORIAS,
            'metodosPago' => Gasto::METODOS_PAGO,
            'hoy' => today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(Gasto::CATEGORIAS)],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'fecha' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(Gasto::METODOS_PAGO)],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        Gasto::query()->create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Gasto registrado.');
    }

    public function destroy(Gasto $gasto): RedirectResponse
    {
        $gasto->delete();

        return back()->with('success', 'Gasto eliminado.');
    }
}
