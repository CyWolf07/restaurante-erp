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
        'inventory_cost_captured_at',
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
            'inventory_cost_captured_at' => 'datetime',
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

    public function scopePaidOn($query, \Illuminate\Support\Carbon $date)
    {
        return $query->paid()->whereBetween(
            \Illuminate\Support\Facades\DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'),
            [$date->copy()->startOfDay(), $date->copy()->endOfDay()]
        );
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
        $this->load('details.modifiers');

        $subtotal = \Brick\Math\BigDecimal::zero();
        foreach ($this->details as $detail) {
            $subtotal = $subtotal->plus($detail->subtotal);
            foreach ($detail->modifiers as $modifier) {
                $subtotal = $subtotal->plus($modifier->subtotal);
            }
        }
        $tax = $subtotal->multipliedBy((string) config('app.tax_rate', 0.16))
            ->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        if ($subtotal->plus($tax)->isGreaterThan('9999999999.99') || $tax->isNegative()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['order' => 'El total o el impuesto de la cuenta no es válido. Revisa precios, cantidades y configuración.']);
        }

        $this->update([
            'subtotal' => (string) $subtotal->toScale(2),
            'tax'      => (string) $tax,
            'total'    => (string) $subtotal->plus($tax)->toScale(2),
        ]);
    }
}
