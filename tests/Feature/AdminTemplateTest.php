<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_template_is_served_as_an_inertia_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Index', shouldExist: false));
    }
}
