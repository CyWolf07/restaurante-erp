<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'purpose', 'connection_type', 'address', 'port',
        'paper_width', 'is_default', 'active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'port'        => 'integer',
            'paper_width' => 'integer',
            'is_default'  => 'boolean',
            'active'      => 'boolean',
        ];
    }

    public function tablesAsKitchen()
    {
        return $this->hasMany(RestaurantTable::class, 'kitchen_printer_id');
    }

    public function tablesAsReceipt()
    {
        return $this->hasMany(RestaurantTable::class, 'receipt_printer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    public function getPurposeLabelAttribute(): string
    {
        return match ($this->purpose) {
            'kitchen' => 'Cocina (comandas)',
            'receipt' => 'Caja (tickets)',
            'bar'     => 'Bar',
            default   => $this->purpose,
        };
    }

    public function getConnectionLabelAttribute(): string
    {
        return match ($this->connection_type) {
            'network' => "Red: {$this->address}:{$this->port}",
            'windows' => "Windows: {$this->address}",
            default   => $this->connection_type,
        };
    }
}
