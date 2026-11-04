<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Administracion/Catalogo/Catalogo', [
            'categories' => Category::query()
                ->withCount('dishes')
                ->withCount(['dishes as archived_dishes_count' => fn (Builder $query) => $query->onlyTrashed()])
                ->orderBy('name')
                ->get(),
            'dishes' => Dish::query()->with('category')->orderBy('name')->get(),
            'archivedDishes' => Dish::onlyTrashed()->with('category')->orderBy('name')->get(),
        ]);
    }
}
