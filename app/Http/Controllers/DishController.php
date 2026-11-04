<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DishController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'category_id' => ['required', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif,avif', 'max:4096'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('dishes', 'public');
        }

        Dish::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'price' => $validated['price'],
            'image' => $imagePath,
            'is_available' => $request->boolean('is_available', true),
        ]);

        return redirect()->back()->with('success', 'Platillo agregado exitosamente.');
    }

    public function update(Request $request, Dish $dish): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'category_id' => ['required', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif,avif', 'max:4096'],
            'is_available' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $oldImage = $dish->image;
        $newImage = $request->hasFile('image')
            ? $request->file('image')->store('dishes', 'public')
            : null;
        $removeOldImage = $request->boolean('remove_image') && ! $newImage;

        $dish->name = $validated['name'];
        $dish->price = $validated['price'];
        $dish->category_id = $validated['category_id'];
        if ($newImage) {
            $dish->image = $newImage;
        } elseif ($removeOldImage) {
            $dish->image = null;
        }
        if (! $dish->track_stock && $request->has('is_available')) {
            $dish->is_available = $request->boolean('is_available');
        }
        $dish->save();

        if (($newImage || $removeOldImage) && $oldImage && Storage::disk('public')->exists($oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->back()->with('success', 'Platillo actualizado exitosamente.');
    }

    public function toggleAvailability(Dish $dish): RedirectResponse
    {
        if ($dish->track_stock) {
            return back()->withErrors([
                'availability' => 'La disponibilidad de este producto se controla desde el inventario.',
            ]);
        }

        $dish->is_available = ! $dish->is_available;
        $dish->save();

        return redirect()->back()->with('success', 'Disponibilidad actualizada.');
    }

    public function destroy(Dish $dish): RedirectResponse
    {
        $dish->delete();

        return redirect()->back()->with('success', 'Platillo retirado del catálogo.');
    }

    public function restore(int $dish): RedirectResponse
    {
        $archivedDish = Dish::withTrashed()->findOrFail($dish);
        $archivedDish->restore();

        return redirect()->back()->with('success', 'Platillo restaurado.');
    }
}
