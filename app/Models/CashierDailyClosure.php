<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierDailyClosure extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'fiscal_date',
        'cashier_id',
        'closed_at',
        'total_sales',
        'total_tax',
        'total_net',
        'total_orders_count',
        'cancelled_orders_count',
        'total_cancelled_amount',
        'expenses',
        'cash_count_summary',
        'base_cash_total',
        'change_cash_total',
        'sales_cash_total',
        'declared_cash_total',
        'cash_difference',
        'total_expenses',
        'expected_cash_total',
        'report_z_total_sales',
        'difference_vs_report_z',
        'pdf_local_path',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_date'             => 'date',
            'closed_at'               => 'datetime',
            'total_sales'             => 'decimal:2',
            'total_tax'               => 'decimal:2',
            'total_net'               => 'decimal:2',
            'total_cancelled_amount'  => 'decimal:2',
            'expenses'                => 'array',
            'cash_count_summary'      => 'array',
            'base_cash_total'         => 'decimal:2',
            'change_cash_total'       => 'decimal:2',
            'sales_cash_total'        => 'decimal:2',
            'declared_cash_total'     => 'decimal:2',
            'cash_difference'         => 'decimal:2',
            'total_expenses'          => 'decimal:2',
            'expected_cash_total'     => 'decimal:2',
            'report_z_total_sales'    => 'decimal:2',
            'difference_vs_report_z'  => 'decimal:2',
        ];
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
