<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'color', 'sort_order'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
