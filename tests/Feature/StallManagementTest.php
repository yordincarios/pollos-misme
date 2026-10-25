<?php

namespace Tests\Feature;

use App\Models\Stall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StallManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_stalls(): void
    {
        $this->get('/admin/stalls')->assertRedirectToRoute('login');
    }

    public function test_authenticated_staff_can_create_edit_and_deactivate_a_stall(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/admin/stalls', [
            'name' => 'Puesto Central',
            'address' => 'Mercado Central',
            'latitude' => '-17.3935000',
            'longitude' => '-66.1570000',
        ])->assertRedirectToRoute('admin.stalls.index');

        $stall = Stall::query()->firstOrFail();
        $this->assertDatabaseHas('stalls', [
            'id' => $stall->id,
            'name' => 'Puesto Central',
            'is_active' => true,
        ]);

        $this->patch("/admin/stalls/{$stall->id}", [
            'name' => 'Puesto Norte',
            'address' => 'Avenida Norte',
            'latitude' => '-17.3900000',
            'longitude' => '-66.1500000',
        ])->assertRedirectToRoute('admin.stalls.index');

        $this->patch("/admin/stalls/{$stall->id}/availability", ['is_active' => false])
            ->assertRedirectToRoute('admin.stalls.index');

        $this->assertDatabaseHas('stalls', [
            'id' => $stall->id,
            'name' => 'Puesto Norte',
            'is_active' => false,
        ]);
    }

    public function test_stall_coordinates_must_be_within_valid_ranges(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from('/admin/stalls')->post('/admin/stalls', [
            'name' => 'Puesto inválido',
            'address' => 'Sin dirección válida',
            'latitude' => '91',
            'longitude' => '-66.157',
        ])->assertSessionHasErrors('latitude');

        $this->assertDatabaseCount('stalls', 0);
    }

    public function test_stall_page_shows_saved_locations_as_map_data(): void
    {
        $this->actingAs(User::factory()->create());
        $stall = Stall::factory()->create(['name' => 'Puesto Central']);

        $this->get('/admin/stalls')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Stalls/Index', shouldExist: false)
                ->where('stalls.0.id', $stall->id)
                ->where('stalls.0.name', 'Puesto Central')
                ->where('stalls.0.latitude', $stall->latitude));
    }
}
