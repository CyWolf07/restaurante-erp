<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\Supply;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $products = Product::with(['category', 'recipes.supply'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->whereLike('name', '%'.$term.'%'))
            ->orderBy('sort_order')->orderBy('name')->paginate(25)->withQueryString();
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

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            $this->syncRecipes($product, $request->input('recipes', []));
            app(AuditService::class)->record('product', $product->id, 'product_created', ['name' => $product->name, 'recipes' => $request->input('recipes', [])]);

            return $product;
        });

        return back()->with('success', "Plato «{$product->name}» creado.");
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $oldImage = $product->image_path;
        DB::transaction(function () use ($product, $data, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = ['name' => $locked->name, 'price' => $locked->price, 'recipes' => $locked->recipes()->get(['supply_id', 'quantity_required'])->toArray()];
            $locked->update($data);
            if ($request->exists('recipes') || $request->boolean('replace_recipes')) {
                $this->syncRecipes($locked, $request->input('recipes', []));
                $locked->increment('recipe_version');
            }
            app(AuditService::class)->record('product', $locked->id, 'product_updated', [
                'before' => $before, 'after' => ['name' => $locked->name, 'price' => $locked->price,
                    'recipe_version' => $locked->recipe_version, 'recipes' => $locked->recipes()->get(['supply_id', 'quantity_required'])->toArray()],
            ]);
        });
        if (isset($data['image_path']) && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return back()->with('success', "Plato «{$product->name}» actualizado.");
    }

    public function destroy(Product $product)
    {
        $product->update(['active' => false]);

        return back()->with('success', 'Plato desactivado.');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'tax_type' => 'nullable|in:IVA,INC,excluded,exempt',
            'tax_rate' => 'nullable|numeric|decimal:0,4|min:0|max:1',
            'preparation_time' => 'required|integer|min:0',
            'recipe_instructions' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:4096',
            'recipes' => 'sometimes|array',
            'replace_recipes' => 'sometimes|boolean',
            'recipes.*.supply_id' => 'required|uuid|distinct|exists:supplies,id',
            'recipes.*.quantity_required' => 'required|numeric|min:0.0001',
        ]);
        if (! empty($data['tax_type']) && ! isset($data['tax_rate'])) {
            throw ValidationException::withMessages(['tax_rate' => 'Selecciona explícitamente la tasa del impuesto; para excluido/exento usa 0.']);
        }
        if (in_array($data['tax_type'] ?? null, ['excluded', 'exempt'], true) && (float) ($data['tax_rate'] ?? 0) !== 0.0) {
            throw ValidationException::withMessages(['tax_rate' => 'Los productos excluidos o exentos deben tener tasa cero.']);
        }

        return $data;
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
                    'product_id' => $product->id,
                    'supply_id' => $row['supply_id'],
                    'quantity_required' => $row['quantity_required'],
                ]);
            }
        });
    }
}
