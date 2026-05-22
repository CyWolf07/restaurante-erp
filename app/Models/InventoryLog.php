<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    // Append-only: sin updated_at
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'supply_id',
        'type',
        'quantity',
        'stock_after',
        'user_id',
        'order_id',
        'order_detail_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:4',
            'stock_after' => 'decimal:4',
            'created_at'  => 'datetime',
        ];
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'sale_reserved'         => 'Reserva Venta (1ª conf.)',
            'sale_confirmed'        => 'Venta Confirmada (2ª conf.)',
            'sale_consumption'      => 'Consumo por Venta',
            'sale_reversal'         => 'Reversión Venta',
            'purchase_entry'        => 'Entrada por Compra',
            'manual_waste'          => 'Merma Manual',
            'supplier_purchase'     => 'Compra a Proveedor',
            'programmer_adjustment' => 'Ajuste Programador',
            default                 => $this->type,
        };
    }

    public function isEntry(): bool  { return $this->quantity > 0; }
    public function isExit(): bool   { return $this->quantity < 0; }
}
