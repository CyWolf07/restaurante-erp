<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Supply;
use App\Services\ProductionService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index()
    {
        return view('production.index', [
            'orders' => ProductionOrder::with('creator')->latest()->paginate(20),
            'products' => Product::where('active', true)->has('recipes')->orderBy('name')->get(),
            'supplies' => Supply::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ProductionService $service)
    {
        $service->create($request->all(), $request->user());

        return back()->with('success', 'Orden de producción creada con copia de la receta.');
    }

    public function complete(Request $request, ProductionOrder $production, ProductionService $service)
    {
        $data = $request->validate(['actual_quantity' => 'required|numeric|min:0.0001|max:999999']);
        $service->complete($production, (float) $data['actual_quantity'], $request->user());

        return back()->with('success', 'Lote cerrado; consumo y entrada registrados.');
    }

    public function cancel(Request $request, ProductionOrder $production, ProductionService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $service->cancel($production, $data['reason'], $request->user());

        return back()->with('success', 'Borrador anulado.');
    }
}
