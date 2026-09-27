<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supply;
use App\Services\CommerceInventoryMetricsService;
use App\Services\InventoryPurchaseService;
use App\Services\InventoryCatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommerceInventoryController extends Controller
{
    public function index(Request $request, CommerceInventoryMetricsService $metrics)
    {
        $query = Supply::query()->orderBy('code')->orderBy('name');

        if ($request->filled('point')) {
            $query->where('point', $request->point);
        }
        if ($request->filled('family')) {
            $query->where('family', $request->family);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->whereLike('code', "%{$q}%")
                  ->orWhereLike('name', "%{$q}%");
            });
        }

        $supplies = $query->paginate(25)->withQueryString();
        $metrics->attachToSupplies($supplies->getCollection());
        $purchases = \App\Models\InventoryPurchase::with('supply', 'user')
            ->latest()->take(30)->get();

        return view('admin.inventory.index', [
            'supplies'  => $supplies,
            'purchases' => $purchases,
            'tab'       => $request->get('tab', 'inventario'),
            'points'    => config('restaurant.points'),
            'families'  => config('restaurant.families'),
            'departments' => config('restaurant.departments'),
            'adjustmentTypes' => config('restaurant.purchase_adjustment_types'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        $data['current_stock'] = $data['current_stock'] ?? 0;
        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['cost_per_unit'] = $data['cost_per_unit'] ?? $data['pvp'];
        $data['active'] = true;

        app(InventoryCatalogService::class)->create($data, $request->user()->id);

        return redirect()->route('admin.inventory.index', ['tab' => 'inventario'])
            ->with('success', "Producto {$data['code']} registrado en inventario.");
    }

    public function update(Request $request, Supply $supply)
    {
        $data = $this->validateProduct($request, $supply);

        $data['active'] = $request->boolean('active');
        $data['department_number'] = $data['department_number'] ?? null;

        app(InventoryCatalogService::class)->update($supply, $data);

        return redirect()
            ->back()
            ->with('success', "Producto {$supply->code} actualizado correctamente.")
            ->with('open_edit', $supply->id);
    }

    public function adjust(Request $request, Supply $supply, InventoryCatalogService $catalog)
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|not_in:0',
            'expected_stock' => 'required|numeric',
            'reason' => 'required|string|min:5|max:500',
        ]);
        $catalog->adjust($supply, (float) $data['quantity'], (float) $data['expected_stock'], $data['reason'], $request->user()->id);

        return back()->with('success', 'Ajuste registrado con su motivo y responsable.');
    }

    private function validateProduct(Request $request, ?Supply $supply = null): array
    {
        $familyKeys = implode(',', array_keys(config('restaurant.families', [])));
        $pointKeys = implode(',', array_keys(config('restaurant.points', [])));

        return $request->validate([
            'code'              => [
                'required', 'string', 'max:50',
                Rule::unique('supplies', 'code')->ignore($supply?->id),
            ],
            'name'              => 'required|string|max:255',
            'point'             => "required|in:{$pointKeys}",
            'location'          => 'nullable|string|max:255',
            'department_number' => 'nullable|integer|between:1,8',
            'family'            => "required|in:{$familyKeys}",
            'pvp'               => 'required|numeric|min:0',
            'unit_type'         => 'required|in:gram,milliliter,unit',
            'current_stock'     => 'nullable|numeric|min:0',
            'physical_count'    => 'nullable|numeric|min:0',
            'min_stock'         => 'nullable|numeric|min:0',
            'cost_per_unit'     => 'nullable|numeric|min:0',
            'supplier'          => 'nullable|string|max:255',
        ]);
    }

    public function destroy(Supply $supply)
    {
        $hasHistory = $supply->inventoryLogs()->exists()
            || \App\Models\ProductionOrder::where('output_supply_id', $supply->id)->exists()
            || $supply->purchases()->exists()
            || $supply->recipes()->exists()
            || $supply->physicalInventoryDetails()->exists();

        if ($hasHistory) {
            $supply->update(['active' => false]);
            return redirect()->back()
                ->with('warning', "«{$supply->code}» tiene movimientos registrados y fue desactivado (no eliminado).");
        }

        $code = $supply->code;
        $supply->delete();

        return redirect()->back()
            ->with('success', "Producto {$code} eliminado del inventario.");
    }

    public function storePurchase(Request $request, InventoryPurchaseService $service)
    {
        $data = $request->validate([
            'code'            => 'required|string|exists:supplies,code',
            'operation_key'   => 'required|uuid',
            'supplier'        => 'required|string|max:255',
            'purchase_date'   => 'required|date',
            'adjustment_type' => 'required|in:compra,bonificacion,inventario,reposicion',
            'quantity'        => 'required|numeric|min:0.0001',
            'unit_value'      => 'required|numeric|min:0',
            'invoice_number'  => 'nullable|string|max:100',
            'point'           => 'required|in:el_muelle,bocagrande,oficinas,bodega',
        ]);

        $service->register($data, $request->user());

        return redirect()->route('admin.inventory.index', ['tab' => 'compras'])
            ->with('success', 'Compra registrada e inventario actualizado.');
    }
}
