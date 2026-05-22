<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicalInventory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'admin_id',
        'recorded_at',
        'notes',
        'period_from',
        'period_to',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'period_from' => 'date',
            'period_to'   => 'date',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function details()
    {
        return $this->hasMany(PhysicalInventoryDetail::class);
    }

    public function getCriticalItemsAttribute()
    {
        return $this->details()->where('criticality', 'red')->get();
    }

    public function getTotalFinancialImpactAttribute(): float
    {
        return (float) $this->details()->sum('financial_impact');
    }
}
