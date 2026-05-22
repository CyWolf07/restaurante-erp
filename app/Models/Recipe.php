<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    // BigInt PK — no usa HasUuids
    protected $fillable = [
        'product_id',
        'supply_id',
        'quantity_required',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }
}
