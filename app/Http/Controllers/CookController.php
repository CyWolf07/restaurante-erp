<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class CookController extends Controller
{
    public function recipes()
    {
        $categories = ProductCategory::orderBy('sort_order')
            ->with(['products' => fn($q) => $q->where('active', true)->orderBy('sort_order')])
            ->get();

        $products = Product::active()->with('category')->orderBy('sort_order')->get();

        return view('cook.recipes', compact('categories', 'products'));
    }

    public function recipeDetail(Product $product)
    {
        $product->load(['recipes.supply', 'category']);
        return view('cook.recipe-detail', compact('product'));
    }
}
