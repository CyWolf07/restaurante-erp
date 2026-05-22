<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'recipes.supply'])->orderBy('sort_order')->orderBy('name')->get();
        $categories = ProductCategory::orderBy('sort_order')->get();
        $supplies = Supply::active()->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories', 'supplies'));
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $data['sort_order'] = Product::max('sort_order') + 1;
        $data['active'] = true;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        $this->syncRecipes($product, $request->input('recipes', []));

        return back()->with('success', "Plato «{$product->name}» creado.");
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $this->syncRecipes($product, $request->input('recipes', []));

        return back()->with('success', "Plato «{$product->name}» actualizado.");
    }

    public function destroy(Product $product)
    {
        $product->update(['active' => false]);

        return back()->with('success', 'Plato desactivado.');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id'          => 'nullable|exists:product_categories,id',
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'price'                => 'required|numeric|min:0',
            'preparation_time'     => 'required|integer|min:0',
            'recipe_instructions'  => 'nullable|string',
            'active'               => 'sometimes|boolean',
            'image'                => 'nullable|image|max:4096',
        ]);
    }

    private function syncRecipes(Product $product, array $recipes): void
    {
        DB::transaction(function () use ($product, $recipes) {
            Recipe::where('product_id', $product->id)->delete();

            foreach ($recipes as $row) {
                if (empty($row['supply_id']) || empty($row['quantity_required'])) {
                    continue;
                }
                Recipe::create([
                    'product_id'         => $product->id,
                    'supply_id'          => $row['supply_id'],
                    'quantity_required'  => $row['quantity_required'],
                ]);
            }
        });
    }
}
