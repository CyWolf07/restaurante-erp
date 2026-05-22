<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_type',
        'current_stock',
        'physical_count',
        'min_stock',
        'cost_per_unit',
        'pvp',
        'supplier',
        'point',
        'location',
        'department_number',
        'family',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'current_stock'  => 'decimal:4',
            'physical_count' => 'decimal:4',
            'min_stock'      => 'decimal:4',
            'cost_per_unit'     => 'decimal:4',
            'pvp'               => 'decimal:2',
            'department_number' => 'integer',
            'active'            => 'boolean',
        ];
    }

    public function purchases()
    {
        return $this->hasMany(InventoryPurchase::class);
    }

    // Relaciones
    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'recipes')
                    ->withPivot('quantity_required')
                    ->withTimestamps();
    }

    public function modifiers()
    {
        return $this->hasMany(Modifier::class);
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function physicalInventoryDetails()
    {
        return $this->hasMany(PhysicalInventoryDetail::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // Helpers
    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->min_stock;
    }

    public function getUnitLabelAttribute(): string
    {
        return match ($this->unit_type) {
            'gram'       => 'g',
            'milliliter' => 'ml',
            'unit'       => 'unidad(es)',
            default      => $this->unit_type,
        };
    }

    public function getPointLabelAttribute(): string
    {
        return config("restaurant.points.{$this->point}", $this->point ?? '—');
    }

    public function getFamilyLabelAttribute(): string
    {
        return config("restaurant.families.{$this->family}", $this->family ?? '—');
    }

    public function getDepartmentLabelAttribute(): string
    {
        if ($this->department_number === null) {
            return '—';
        }

        $name = config("restaurant.departments.{$this->department_number}");

        return $name
            ? "{$this->department_number} — {$name}"
            : (string) $this->department_number;
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return $this->description ?: $this->name;
    }

    /**
     * Calcula el stock teórico sumando todos los inventory_logs desde el inicio.
     * Usado por el servicio de reparación de integridad.
     */
    public function calculateTheoreticalStock(): float
    {
        return (float) $this->inventoryLogs()->sum('quantity');
    }
}
