<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipe_snapshot' => 'array', 'planned_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4', 'total_cost' => 'decimal:4', 'completed_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function outputSupply()
    {
        return $this->belongsTo(Supply::class, 'output_supply_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
