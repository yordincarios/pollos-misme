<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    use HasFactory;

    public const CATEGORIAS = ['insumos', 'servicios', 'personal', 'mantenimiento', 'otros'];

    public const METODOS_PAGO = ['efectivo', 'transferencia', 'qr'];

    protected $fillable = [
        'descripcion',
        'categoria',
        'monto',
        'fecha',
        'metodo_pago',
        'notas',
        'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
