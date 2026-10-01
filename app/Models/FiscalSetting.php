<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalSetting extends Model
{
    protected $fillable = ['id', 'require_hold_reason'];

    protected function casts(): array
    {
        return ['require_hold_reason' => 'boolean'];
    }

    public static function requiresHoldReason(): bool
    {
        return static::whereKey(1)->value('require_hold_reason') ?? true;
    }
}
