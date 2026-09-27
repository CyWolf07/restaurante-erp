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
        'type',
        'group',
        'sort_order',
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
            'sort_order'     => 'integer',
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

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'category_modifiers', 'modifier_id', 'category_id')
            ->withPivot('enabled', 'sort_order')
            ->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifiers')
            ->withPivot('enabled', 'sort_order')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOptions($query)
    {
        return $query->where('type', 'option');
    }

    public function scopeAddons($query)
    {
        return $query->where('type', 'addon');
    }

    /** Verifica si este modificador afecta el inventario */
    public function affectsInventory(): bool
    {
        return $this->supply_id !== null && $this->extra_quantity > 0;
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'option' ? 'Opción cocina' : 'Adicional con precio';
    }
}
