<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyReport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'period_year',
        'period_month',
        'period_start',
        'period_end',
        'total_sales',
        'total_ingredient_cost',
        'gross_profit',
        'total_orders_count',
        'total_products_sold',
        'closed_by',
        'pdf_local_path',
        'compressed_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'period_year'           => 'integer',
            'period_month'          => 'integer',
            'period_start'          => 'date',
            'period_end'            => 'date',
            'total_sales'           => 'decimal:2',
            'total_ingredient_cost' => 'decimal:2',
            'gross_profit'          => 'decimal:2',
            'total_orders_count'    => 'integer',
            'total_products_sold'   => 'integer',
        ];
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function getSnapshotDataAttribute(): array
    {
        if (!$this->compressed_snapshot) {
            return [];
        }

        $json = gzdecode(base64_decode($this->compressed_snapshot, true) ?: '');

        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function setSnapshotDataAttribute(array $snapshot): void
    {
        $this->attributes['compressed_snapshot'] = base64_encode(gzencode(json_encode($snapshot)));
    }
}
