<?php

namespace Tests\Feature;

use Tests\TestCase;

class RetiredAdminModulesTest extends TestCase
{
    public function test_removed_stall_order_and_payment_settings_routes_no_longer_exist(): void
    {
        $this->get('/admin/stalls')->assertNotFound();
        $this->post('/admin/stalls')->assertNotFound();
        $this->get('/admin/orders')->assertNotFound();
        $this->post('/admin/orders')->assertNotFound();
        $this->patch('/admin/orders/1/status')->assertNotFound();
        $this->post('/admin/orders/1/payment')->assertNotFound();
        $this->post('/payment-settings/qr')->assertNotFound();
        $this->delete('/payment-settings/qr')->assertNotFound();
    }
}
