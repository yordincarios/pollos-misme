<?php

namespace App\Http\Controllers;

use App\Models\Stall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StallController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Stalls/Index', [
            'stalls' => Stall::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        Stall::create($validated + ['is_active' => true]);

        return redirect()->route('admin.stalls.index')->with('success', 'Puesto registrado.');
    }

    public function update(Request $request, Stall $stall): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $stall->update($validated);

        return redirect()->route('admin.stalls.index')->with('success', 'Puesto actualizado.');
    }

    public function updateAvailability(Request $request, Stall $stall): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $stall->update(['is_active' => $validated['is_active']]);

        return redirect()->route('admin.stalls.index')->with('success', 'Disponibilidad del puesto actualizada.');
    }
}
