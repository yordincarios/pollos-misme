<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_admin_and_pos(): void
    {
        $this->get('/admin')->assertRedirectToRoute('login');
        $this->get('/pos')->assertRedirectToRoute('login');
    }

    public function test_staff_can_sign_in_with_email_and_four_digit_pin(): void
    {
        $user = User::factory()->create(['password' => '4826']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => '4826',
        ])->assertRedirectToRoute('admin.index');

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_lands_on_admin_after_login_even_if_they_requested_pos_first(): void
    {
        $user = User::factory()->create(['password' => '4826']);
        $this->get('/pos')->assertRedirectToRoute('login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => '4826',
        ])->assertRedirectToRoute('admin.index');
    }

    public function test_invalid_pin_does_not_authenticate_user(): void
    {
        $user = User::factory()->create(['password' => '4826']);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => '1111',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirectToRoute('login');

        $this->assertGuest();
    }
}
