<?php

namespace App\Models;

use Database\Factories\PaymentSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentSetting extends Model
{
    /** @use HasFactory<PaymentSettingFactory> */
    use HasFactory;

    public const CURRENT_ID = 1;

    public $incrementing = false;

    protected $attributes = [
        'id' => self::CURRENT_ID,
    ];

    protected $fillable = [
        'qr_image_path',
    ];

    public static function qrImagePath(): ?string
    {
        $path = static::query()->whereKey(self::CURRENT_ID)->value('qr_image_path');

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    public static function qrImageUrl(): ?string
    {
        $path = self::qrImagePath();

        return $path === null ? null : '/storage/'.$path;
    }
}
