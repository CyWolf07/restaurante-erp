<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'number', 'name', 'zone', 'capacity',
        'kitchen_printer_id', 'receipt_printer_id',
        'grid_row', 'grid_col', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'number'   => 'integer',
            'capacity' => 'integer',
            'grid_row' => 'integer',
            'grid_col' => 'integer',
            'active'   => 'boolean',
        ];
    }

    public function kitchenPrinter()
    {
        return $this->belongsTo(Printer::class, 'kitchen_printer_id');
    }

    public function receiptPrinter()
    {
        return $this->belongsTo(Printer::class, 'receipt_printer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('number');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "Mesa {$this->number}";
    }

    public function activeOrder()
    {
        return Order::forTable($this->number)->latest()->first();
    }

    public function getStatusColorAttribute(): string
    {
        $order = $this->activeOrder();
        if (!$order) {
            return 'free';
        }
        return match ($order->status) {
            'pending', 'in_kitchen' => 'occupied-red',
            'ready'                 => 'billing-green',
            default                 => 'free',
        };
    }
}
