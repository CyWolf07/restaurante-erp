<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'price',
        'supply_id',
        'extra_quantity',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'extra_quantity' => 'decimal:4',
            'active'         => 'boolean',
        ];
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }

    public function orderDetailModifiers()
    {
        return $this->hasMany(OrderDetailModifier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Verifica si este modificador afecta el inventario
     */
    public function affectsInventory(): bool
    {
        return $this->supply_id !== null && $this->extra_quantity > 0;
    }
}
