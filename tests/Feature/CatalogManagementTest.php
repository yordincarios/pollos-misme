<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_catalog_management(): void
    {
        $this->get('/admin/catalog')->assertRedirectToRoute('login');
    }

    public function test_authenticated_staff_can_access_catalog_management(): void
    {
        $this->actingAs(User::factory()->create());
        $dish = Dish::factory()->create(['name' => 'Silpancho']);

        $this->get('/admin/catalog')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Administracion/Catalogo/Catalogo', shouldExist: false)
                ->where('dishes.0.id', $dish->id)
                ->where('dishes.0.name', 'Silpancho')
                ->has('categories', 1)
                ->has('archivedDishes', 0));
    }
}
