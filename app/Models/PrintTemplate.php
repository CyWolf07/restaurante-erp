<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'settings',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function settingsFor(string $slug): array
    {
        $record = static::where('slug', $slug)->first();
        $defaults = config("ticket_print.defaults.{$slug}", []);

        return array_merge($defaults, $record?->settings ?? []);
    }

    public static function ensureDefaults(): void
    {
        foreach (config('ticket_print.types', []) as $slug => $label) {
            static::firstOrCreate(
                ['slug' => $slug],
                [
                    'name'     => $label,
                    'settings' => config("ticket_print.defaults.{$slug}", []),
                ]
            );
        }
    }
}
