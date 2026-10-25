<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PaymentQrSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_upload_or_remove_the_payment_qr(): void
    {
        Storage::fake('public');

        $this->post('/payment-settings/qr', ['image' => $this->fakeQrImage()])
            ->assertRedirectToRoute('login');
        $this->delete('/payment-settings/qr')->assertRedirectToRoute('login');

        $this->assertDatabaseCount('payment_settings', 0);
    }

    public function test_authenticated_staff_can_upload_a_qr_and_pos_receives_its_url(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('public');

        $this->from('/pos')->post('/payment-settings/qr', [
            'image' => $this->fakeQrImage(),
        ])->assertRedirect('/pos');

        $setting = PaymentSetting::query()->findOrFail(PaymentSetting::CURRENT_ID);
        Storage::disk('public')->assertExists($setting->qr_image_path);

        $this->get('/pos')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('paymentQrUrl', '/storage/'.$setting->qr_image_path));
    }

    public function test_replacing_a_payment_qr_deletes_the_previous_image(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('public');
        $previousPath = 'payment-qr/previous.png';
        Storage::disk('public')->put($previousPath, 'previous image');
        PaymentSetting::create(['qr_image_path' => $previousPath]);

        $this->from('/pos')->post('/payment-settings/qr', [
            'image' => $this->fakeQrImage('updated-qr.png'),
        ])->assertRedirect('/pos');

        $newPath = PaymentSetting::query()->findOrFail(PaymentSetting::CURRENT_ID)->qr_image_path;
        $this->assertNotSame($previousPath, $newPath);
        Storage::disk('public')->assertMissing($previousPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_payment_qr_upload_rejects_non_image_files(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('public');

        $this->from('/pos')->post('/payment-settings/qr', [
            'image' => UploadedFile::fake()->create('qr.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('payment_settings', 0);
        Storage::disk('public')->assertDirectoryEmpty('payment-qr');
    }

    public function test_staff_can_remove_the_configured_payment_qr(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('public');
        $path = 'payment-qr/current.png';
        Storage::disk('public')->put($path, 'current image');
        PaymentSetting::create(['qr_image_path' => $path]);

        $this->from('/pos')->delete('/payment-settings/qr')->assertRedirect('/pos');

        $this->assertDatabaseHas('payment_settings', [
            'id' => PaymentSetting::CURRENT_ID,
            'qr_image_path' => null,
        ]);
        Storage::disk('public')->assertMissing($path);
    }

    private function fakeQrImage(string $filename = 'merchant-qr.png'): UploadedFile
    {
        $imageBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($filename, $imageBytes);
    }
}
