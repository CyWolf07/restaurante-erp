<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'pin_code',
        'role',
        'active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',
        'pin_hash',
        'pin_lookup',
    ];

    public function setPinCodeAttribute(?string $pin): void
    {
        $this->attributes['pin_code'] = null;
        if ($pin === null || $pin === '') {
            $this->attributes['pin_hash'] = null;
            $this->attributes['pin_lookup'] = null;
            return;
        }
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pin_code' => 'El PIN debe tener entre cuatro y seis dígitos.']);
        }
        $lookup = \App\Support\PinCredential::fingerprint($pin);
        if (static::where('pin_lookup', $lookup)->when($this->exists, fn ($query) => $query->where('id', '<>', $this->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pin_code' => 'Ese PIN ya está asignado.']);
        }
        $this->attributes['pin_hash'] = \Illuminate\Support\Facades\Hash::make($pin);
        $this->attributes['pin_lookup'] = $lookup;
    }

    public function hasPin(): bool
    {
        return ! empty($this->attributes['pin_hash']);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    // Roles
    public function isProgrammer(): bool { return $this->role === 'programmer'; }
    public function isAdministrator(): bool { return $this->role === 'administrator'; }
    public function isCashier(): bool { return $this->role === 'cashier'; }
    public function isWaiter(): bool { return $this->role === 'waiter'; }
    public function isCook(): bool { return $this->role === 'cook'; }
    public function isManager(): bool { return in_array($this->role, ['programmer', 'administrator']); }

    // Relaciones
    public function ordersAsWaiter()
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    public function ordersAsCashier()
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function physicalInventories()
    {
        return $this->hasMany(PhysicalInventory::class, 'admin_id');
    }

    public function dailyReports()
    {
        return $this->hasMany(DailyReportZ::class, 'cashier_id');
    }

    /**
     * Buscar usuario por PIN (para login en terminales locales)
     */
    public static function findByPin(string $pin): ?self
    {
        $user = static::where('pin_lookup', \App\Support\PinCredential::fingerprint($pin))->where('active', true)->first();
        return $user && \Illuminate\Support\Facades\Hash::check($pin, $user->pin_hash) ? $user : null;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'programmer'    => 'Programador',
            'administrator' => 'Administrador',
            'cashier'       => 'Cajero',
            'waiter'        => 'Mesero',
            'cook'          => 'Cocinero',
            default         => $this->role,
        };
    }
}
