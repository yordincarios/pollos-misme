<?php

namespace App\Models;

use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    public const TYPE_ENTRY = 'entry';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_WASTE = 'waste';

    public const TYPE_SALE = 'sale';

    protected $fillable = [
        'dish_id',
        'user_id',
        'sale_id',
        'type',
        'quantity',
        'stock_after',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after' => 'integer',
    ];

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
