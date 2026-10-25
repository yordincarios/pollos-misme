<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
        ]);

        Category::create($validated);

        return back()->with('success', 'Categoría agregada.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        $category->update($validated);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->dishes()->withTrashed()->exists()) {
            return back()->withErrors([
                'category' => 'No se puede eliminar una categoría que todavía tiene platillos asociados.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }
}
