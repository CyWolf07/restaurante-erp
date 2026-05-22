<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetailModifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_detail_id',
        'modifier_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal'   => 'decimal:2',
        ];
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}
