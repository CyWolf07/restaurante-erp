<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;

class CookController extends Controller
{
    public function orders()
    {
        $orders = Order::whereIn('status', ['in_kitchen', 'ready'])
            ->with(['details.product', 'details.modifiers.modifier', 'waiter', 'restaurantTable'])
            ->orderBy('kitchen_sent_at')->paginate(25);

        return view('cook.orders', compact('orders'));
    }

    public function recipes()
    {
        $categories = ProductCategory::orderBy('sort_order')
            ->with(['products' => fn ($q) => $q->where('active', true)->orderBy('sort_order')])
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
