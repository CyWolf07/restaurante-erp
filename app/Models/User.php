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
    ];

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
        return static::where('pin_code', $pin)->where('active', true)->first();
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
