<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPurchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'supply_id', 'code', 'supplier', 'purchase_date', 'adjustment_type',
        'quantity', 'unit_value', 'total_value', 'invoice_number', 'point',
        'user_id', 'inventory_log_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'quantity'      => 'decimal:4',
            'unit_value'    => 'decimal:2',
            'total_value'   => 'decimal:2',
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

    public function getAdjustmentTypeLabelAttribute(): string
    {
        return config("restaurant.purchase_adjustment_types.{$this->adjustment_type}", $this->adjustment_type);
    }

    public function getPointLabelAttribute(): string
    {
        return config("restaurant.points.{$this->point}", $this->point);
    }
}
