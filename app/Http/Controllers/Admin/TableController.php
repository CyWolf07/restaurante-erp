<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Services\PosOperationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TableController extends Controller
{
    public function index()
    {
        $tables = RestaurantTable::with(['kitchenPrinter', 'receiptPrinter'])
            ->orderBy('sort_order')->orderBy('number')->get();
        $printers = Printer::active()->get();

        return view('admin.tables.index', compact('tables', 'printers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number' => 'required|integer|min:1|unique:restaurant_tables,number',
            'name' => 'nullable|string|max:100',
            'zone' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'kitchen_printer_id' => 'nullable|exists:printers,id',
            'receipt_printer_id' => 'nullable|exists:printers,id',
            'grid_row' => 'nullable|integer|min:0',
            'grid_col' => 'nullable|integer|min:0',
        ]);

        $data['sort_order'] = RestaurantTable::max('sort_order') + 1;
        $data['active'] = true;

        RestaurantTable::create($data);

        return back()->with('success', "Mesa #{$data['number']} creada correctamente.");
    }

    public function update(Request $request, RestaurantTable $table)
    {
        $data = $request->validate([
            'number' => 'required|integer|min:1|unique:restaurant_tables,number,'.$table->id,
            'name' => 'nullable|string|max:100',
            'zone' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
            'kitchen_printer_id' => 'nullable|exists:printers,id',
            'receipt_printer_id' => 'nullable|exists:printers,id',
            'grid_row' => 'nullable|integer|min:0',
            'grid_col' => 'nullable|integer|min:0',
            'active' => 'boolean',
        ]);

        app(PosOperationService::class)->run(function () use ($table, $data) {
            $table->refresh();
            if (Order::forTable($table->number)->exists()
                && ((int) $data['number'] !== $table->number || (isset($data['active']) && ! $data['active']))) {
                throw ValidationException::withMessages(['number' => 'No se puede renumerar ni desactivar una mesa ocupada. Traslada o cierra su orden primero.']);
            }
            $table->update($data);
        }, requireOpen: false);

        return back()->with('success', 'Mesa actualizada.');
    }

    public function destroy(RestaurantTable $table)
    {
        app(PosOperationService::class)->run(function () use ($table) {
            $table->refresh();
            if (Order::forTable($table->number)->exists()) {
                throw ValidationException::withMessages(['table' => 'No se puede eliminar: la mesa tiene una orden activa.']);
            }
            $table->delete();
        }, requireOpen: false);

        return back()->with('success', 'Mesa eliminada.');
    }
}
