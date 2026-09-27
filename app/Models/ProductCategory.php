<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProductCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'color', 'sort_order'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function modifiers()
    {
        return $this->belongsToMany(Modifier::class, 'category_modifiers', 'category_id', 'modifier_id')
            ->withPivot('enabled', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /** Retorna las opciones activas asignadas a esta categoría, agrupadas */
    public function activeOptions(): Collection
    {
        return $this->modifiers()
            ->where('modifiers.active', true)
            ->where('modifiers.type', 'option')
            ->wherePivot('enabled', true)
            ->get()
            ->groupBy('group');
    }
}
