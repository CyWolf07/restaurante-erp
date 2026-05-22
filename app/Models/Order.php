<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'table_number',
        'restaurant_table_id',
        'waiter_id',
        'kitchen_sent_by',
        'kitchen_sent_at',
        'cashier_id',
        'status',
        'subtotal',
        'tax',
        'total',
        'cancellation_reason',
        'locked_at',
        'inventory_reserved_at',
        'inventory_confirmed_at',
        'preticket_printed',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'   => 'decimal:2',
            'tax'        => 'decimal:2',
            'total'      => 'decimal:2',
            'locked_at'              => 'datetime',
            'kitchen_sent_at'        => 'datetime',
            'inventory_reserved_at'  => 'datetime',
            'inventory_confirmed_at' => 'datetime',
            'preticket_printed'      => 'boolean',
        ];
    }

    // Relaciones
    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function kitchenSentBy()
    {
        return $this->belongsTo(User::class, 'kitchen_sent_by');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function restaurantTable()
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_kitchen', 'ready']);
    }

    public function scopeForTable($query, int $tableNumber)
    {
        return $query->where('table_number', $tableNumber)->active();
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // Helpers de estado
    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isInKitchen(): bool { return $this->status === 'in_kitchen'; }
    public function isReady(): bool     { return $this->status === 'ready'; }
    public function isPaid(): bool      { return $this->status === 'paid'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isLocked(): bool    { return $this->locked_at !== null; }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'Pendiente / Ocupado',
            'in_kitchen' => 'En cocina',
            'ready'      => 'Por pagar',
            'paid'       => 'Pagado',
            'cancelled'  => 'Cancelado',
            default      => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending', 'in_kitchen' => 'red',
            'ready'                  => 'green',
            'paid'       => 'gray',
            'cancelled'  => 'red',
            default      => 'gray',
        };
    }

    /**
     * Recalcula los totales de la orden sumando los detalles
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('details.modifiers');

        $subtotal = $this->details->sum('subtotal') + $this->details->flatMap->modifiers->sum('subtotal');
        $taxRate  = (float) config('app.tax_rate', 0.16);
        $tax      = round($subtotal * $taxRate, 2);

        $this->update([
            'subtotal' => $subtotal,
            'tax'      => $tax,
            'total'    => $subtotal + $tax,
        ]);
    }
}
