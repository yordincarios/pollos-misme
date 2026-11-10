<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_is_disabled_by_default(): void
    {
        config(['features.gestion_usuarios' => false]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/usuarios')
            ->assertNotFound();
    }

    public function test_user_management_can_be_enabled_with_the_feature_flag(): void
    {
        config(['features.gestion_usuarios' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/usuarios')
            ->assertInertia(fn ($page) => $page->component('Administracion/Usuarios', shouldExist: false));
    }

    public function test_admin_can_create_update_and_delete_users_when_feature_is_enabled(): void
    {
        config(['features.gestion_usuarios' => true]);
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->post('/admin/usuarios', [
            'name' => 'Cajero',
            'email' => 'cajero@pollosmisme.test',
            'password' => '4321',
        ])->assertRedirect();

        $cajero = User::query()->where('email', 'cajero@pollosmisme.test')->firstOrFail();
        $this->assertTrue(Hash::check('4321', $cajero->password));

        $this->patch("/admin/usuarios/{$cajero->id}", [
            'name' => 'Cajero actualizado',
            'email' => 'cajero2@pollosmisme.test',
            'password' => '9876',
        ])->assertRedirect();

        $cajero->refresh();
        $this->assertSame('Cajero actualizado', $cajero->name);
        $this->assertTrue(Hash::check('9876', $cajero->password));

        $this->delete("/admin/usuarios/{$cajero->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $cajero->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        config(['features.gestion_usuarios' => true]);
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->delete("/admin/usuarios/{$admin->id}")->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
