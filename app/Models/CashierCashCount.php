<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierCashCount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'fiscal_date',
        'cashier_id',
        'base_counts',
        'change_counts',
        'sales_counts',
        'base_total',
        'change_total',
        'sales_total',
        'declared_cash_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_date' => 'date',
            'base_counts' => 'array',
            'change_counts' => 'array',
            'sales_counts' => 'array',
            'base_total' => 'decimal:2',
            'change_total' => 'decimal:2',
            'sales_total' => 'decimal:2',
            'declared_cash_total' => 'decimal:2',
        ];
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
