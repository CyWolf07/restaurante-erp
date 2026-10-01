<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FiscalDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'status', 'document_type', 'payment_method', 'subtotal', 'tax', 'total',
        'buyer_data', 'document_snapshot', 'notes', 'hold_reason', 'held_by', 'held_at',
        'provider', 'external_number', 'fiscal_identifier', 'provider_response',
        'submitted_by', 'submitted_at', 'validation_evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'buyer_data' => 'array',
            'document_snapshot' => 'array',
            'provider_response' => 'array',
            'held_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function heldBy()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeUnsent($query)
    {
        return $query->whereIn('status', ['pending_review', 'held_for_correction']);
    }

    public function isUnsent(): bool
    {
        return in_array($this->status, ['pending_review', 'held_for_correction'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_review' => 'Pendiente de revisión',
            'held_for_correction' => 'Retenida para corregir',
            'manually_submitted' => 'Envío conciliado manualmente (sin verificación automática DIAN)',
            default => $this->status,
        };
    }
}
