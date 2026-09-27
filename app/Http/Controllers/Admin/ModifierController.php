<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModifierController extends Controller
{
    public function index()
    {
        $modifiers = Modifier::with('categories')
            ->orderBy('type')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        $categories = ProductCategory::with([
            'modifiers' => fn($q) => $q->where('modifiers.type', 'option'),
        ])->orderBy('sort_order')->get();

        $products = Product::active()
            ->with(['category', 'modifiers' => fn($q) => $q->where('modifiers.type', 'option')])
            ->orderBy('name')
            ->get();

        return view('admin.modifiers.index', compact('modifiers', 'categories', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|in:option,addon',
            'group' => 'nullable|string|max:80',
            'sort_order' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        Modifier::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'group' => $data['group'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'price' => $data['price'] ?? 0,
            'extra_quantity' => 0,
            'active' => true,
        ]);

        return back()->with('success', 'Opcion/modificador creado correctamente.');
    }

    public function update(Request $request, Modifier $modifier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'group' => 'nullable|string|max:80',
            'sort_order' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        $modifier->update([
            'name' => $data['name'],
            'group' => $data['group'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'price' => $data['price'] ?? 0,
        ]);

        return back()->with('success', 'Modificador actualizado.');
    }

    public function destroy(Modifier $modifier)
    {
        $modifier->delete();

        return back()->with('success', 'Modificador eliminado.');
    }

    public function toggleActive(Modifier $modifier)
    {
        $modifier->update(['active' => !$modifier->active]);
        $status = $modifier->active ? 'habilitado' : 'deshabilitado';

        return back()->with('success', "\"{$modifier->name}\" {$status}.");
    }

    public function syncCategory(Request $request, ProductCategory $category)
    {
        $data = $request->validate([
            'modifiers' => 'nullable|array',
            'modifiers.*' => 'exists:modifiers,id',
        ]);

        $ids = collect($data['modifiers'] ?? [])
            ->mapWithKeys(fn($id, $idx) => [$id => $this->pivotData($idx)]);

        $currentAddons = $category->modifiers()
            ->where('modifiers.type', 'addon')
            ->pluck('modifiers.id');

        $addonPivots = $currentAddons
            ->mapWithKeys(fn($id) => [$id => $this->pivotData()]);

        $category->modifiers()->sync($ids->merge($addonPivots));

        return back()->with('success', "Opciones de \"{$category->name}\" actualizadas.");
    }

    public function toggleCategoryOption(Request $request, ProductCategory $category, Modifier $modifier)
    {
        $pivot = $category->modifiers()->where('modifiers.id', $modifier->id)->first();

        if (!$pivot) {
            $category->modifiers()->attach($modifier->id, $this->pivotData(99));

            return back()->with('success', "\"{$modifier->name}\" asignado a {$category->name}.");
        }

        $newEnabled = !$pivot->pivot->enabled;
        $category->modifiers()->updateExistingPivot($modifier->id, ['enabled' => $newEnabled]);
        $status = $newEnabled ? 'habilitada' : 'deshabilitada';

        return back()->with('success', "\"{$modifier->name}\" {$status} en {$category->name}.");
    }

    public function syncProduct(Request $request, Product $product)
    {
        $data = $request->validate([
            'modifiers' => 'nullable|array',
            'modifiers.*' => 'exists:modifiers,id',
        ]);

        $ids = collect($data['modifiers'] ?? [])
            ->mapWithKeys(fn($id, $idx) => [$id => $this->pivotData($idx)]);

        $product->modifiers()->sync($ids);
        $product->update(['uses_product_modifiers' => $ids->isNotEmpty()]);

        return back()->with('success', "Opciones del plato \"{$product->name}\" actualizadas.");
    }

    public function clearProductOverride(Product $product)
    {
        $product->modifiers()->detach();
        $product->update(['uses_product_modifiers' => false]);

        return back()->with('success', "\"{$product->name}\" ahora hereda las opciones de su categoria.");
    }

    private function pivotData(int $sortOrder = 0): array
    {
        return [
            'id' => (string) Str::uuid(),
            'enabled' => true,
            'sort_order' => $sortOrder,
        ];
    }
}
