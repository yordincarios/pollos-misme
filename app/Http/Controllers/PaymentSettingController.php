<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PaymentSettingController extends Controller
{
    public function updateQr(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $newPath = $validated['image']->store('payment-qr', 'public');

        try {
            $oldPath = DB::transaction(function () use ($newPath): ?string {
                $setting = PaymentSetting::query()
                    ->lockForUpdate()
                    ->find(PaymentSetting::CURRENT_ID);

                if (! $setting) {
                    $setting = new PaymentSetting;
                    $setting->id = PaymentSetting::CURRENT_ID;
                }

                $oldPath = $setting->qr_image_path;
                $setting->qr_image_path = $newPath;
                $setting->save();

                return $oldPath;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->back()->with('success', 'QR de pago actualizado.');
    }

    public function destroyQr(): RedirectResponse
    {
        $oldPath = DB::transaction(function (): ?string {
            $setting = PaymentSetting::query()
                ->lockForUpdate()
                ->find(PaymentSetting::CURRENT_ID);

            if (! $setting) {
                return null;
            }

            $oldPath = $setting->qr_image_path;
            $setting->qr_image_path = null;
            $setting->save();

            return $oldPath;
        });

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->back()->with('success', 'QR de pago eliminado.');
    }
}
