<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image_path',
        'recipe_instructions',
        'preparation_time',
        'active',
        'sort_order',
        'uses_product_modifiers',
    ];

    protected function casts(): array
    {
        return [
            'price'                  => 'decimal:2',
            'active'                 => 'boolean',
            'uses_product_modifiers' => 'boolean',
        ];
    }

    // Relaciones
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function supplies()
    {
        return $this->belongsToMany(Supply::class, 'recipes')
                    ->withPivot('quantity_required')
                    ->withTimestamps();
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function modifiers()
    {
        return $this->belongsToMany(Modifier::class, 'product_modifiers')
            ->withPivot('enabled', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path && file_exists(storage_path('app/public/' . $this->image_path))) {
            return asset('storage/' . $this->image_path);
        }
        return asset('images/no-image.png');
    }

    /**
     * Retorna las opciones activas para este plato:
     * - Si uses_product_modifiers=true → usa sus propias opciones (override)
     * - Si no → hereda de su categoría
     * Resultado agrupado por 'group' para renderizar en la UI.
     */
    public function getEffectiveOptions(): Collection
    {
        if ($this->uses_product_modifiers) {
            return $this->modifiers()
                ->where('modifiers.active', true)
                ->where('modifiers.type', 'option')
                ->wherePivot('enabled', true)
                ->get()
                ->groupBy('group');
        }

        // Heredar de categoría
        $this->loadMissing('category');
        if (!$this->category) {
            return collect();
        }

        return $this->category->activeOptions();
    }
}
