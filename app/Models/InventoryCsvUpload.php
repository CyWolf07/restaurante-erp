<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InventoryCsvUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'original_name',
        'stored_path',
        'mime_type',
        'extension',
        'size_bytes',
        'user_id',
        'imported_at',
        'import_summary',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'      => 'integer',
            'imported_at'     => 'datetime',
            'import_summary'  => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getTypeLabelAttribute(): string
    {
        if ($this->extension) {
            return strtoupper($this->extension);
        }

        return match ($this->mime_type) {
            'text/csv', 'application/csv' => 'CSV',
            'text/plain'               => 'TXT',
            'application/vnd.ms-excel' => 'Excel/CSV',
            default                    => $this->mime_type ?? 'Archivo',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->imported_at ? 'Importado' : 'Cargado';
    }

    public function isImported(): bool
    {
        return $this->imported_at !== null;
    }

    public function fullPath(): string
    {
        return Storage::disk('local')->path($this->stored_path);
    }

    public function deleteStoredFile(): void
    {
        if (Storage::disk('local')->exists($this->stored_path)) {
            Storage::disk('local')->delete($this->stored_path);
        }
    }
}
