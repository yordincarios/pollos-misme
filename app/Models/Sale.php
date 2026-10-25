<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'total',
        'cash_received',
        'change_given',
        'payment_method',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_given' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
