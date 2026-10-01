<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportZ extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'daily_reports_z';

    protected $fillable = [
        'fiscal_date',
        'total_sales',
        'total_tax',
        'total_net',
        'total_orders_count',
        'cancelled_orders_count',
        'total_cancelled_amount',
        'cashier_id',
        'pdf_local_path',
        'database_backup_path',
        'summary_data',
        'fiscal_pending_count',
        'fiscal_pending_total',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_date' => 'date',
            'total_sales' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_cancelled_amount' => 'decimal:2',
            'summary_data' => 'array', // JSONB cast automático
            'fiscal_pending_total' => 'decimal:2',
        ];
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function getPdfUrlAttribute(): ?string
    {
        return route('admin.daily-reports.pdf', ['report' => $this->id]);
    }
}
