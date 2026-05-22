<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalInventoryDetail extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'physical_inventory_id',
        'supply_id',
        'theoretical_stock',
        'physical_stock',
        'difference',
        'volume_sold_period',
        'deviation_percentage',
        'financial_impact',
        'cost_per_unit_snapshot',
        'criticality',
    ];

    protected function casts(): array
    {
        return [
            'theoretical_stock'    => 'decimal:4',
            'physical_stock'       => 'decimal:4',
            'difference'           => 'decimal:4',
            'volume_sold_period'   => 'decimal:4',
            'deviation_percentage' => 'decimal:4',
            'financial_impact'     => 'decimal:2',
            'cost_per_unit_snapshot' => 'decimal:4',
        ];
    }

    public function physicalInventory()
    {
        return $this->belongsTo(PhysicalInventory::class);
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }

    public function getCriticalityColorAttribute(): string
    {
        return match ($this->criticality) {
            'green'  => '#22c55e',
            'yellow' => '#f59e0b',
            'red'    => '#ef4444',
            default  => '#6b7280',
        };
    }

    public function getCriticalityLabelAttribute(): string
    {
        return match ($this->criticality) {
            'green'  => 'Estable',
            'yellow' => 'Alerta de Merma',
            'red'    => 'Fuga Crítica',
            default  => 'Desconocido',
        };
    }
}
