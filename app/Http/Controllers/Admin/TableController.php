<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;

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
            'number'              => 'required|integer|min:1|unique:restaurant_tables,number',
            'name'                => 'nullable|string|max:100',
            'zone'                => 'required|string|max:100',
            'capacity'            => 'required|integer|min:1|max:50',
            'kitchen_printer_id'  => 'nullable|exists:printers,id',
            'receipt_printer_id'  => 'nullable|exists:printers,id',
            'grid_row'            => 'nullable|integer|min:0',
            'grid_col'            => 'nullable|integer|min:0',
        ]);

        $data['sort_order'] = RestaurantTable::max('sort_order') + 1;
        $data['active'] = true;

        RestaurantTable::create($data);

        return back()->with('success', "Mesa #{$data['number']} creada correctamente.");
    }

    public function update(Request $request, RestaurantTable $table)
    {
        $data = $request->validate([
            'number'              => 'required|integer|min:1|unique:restaurant_tables,number,' . $table->id,
            'name'                => 'nullable|string|max:100',
            'zone'                => 'required|string|max:100',
            'capacity'            => 'required|integer|min:1|max:50',
            'kitchen_printer_id'  => 'nullable|exists:printers,id',
            'receipt_printer_id'  => 'nullable|exists:printers,id',
            'grid_row'            => 'nullable|integer|min:0',
            'grid_col'            => 'nullable|integer|min:0',
            'active'              => 'boolean',
        ]);

        $table->update($data);

        return back()->with('success', "Mesa actualizada.");
    }

    public function destroy(RestaurantTable $table)
    {
        if ($table->orders()->active()->exists()) {
            return back()->with('error', 'No se puede eliminar: la mesa tiene una orden activa.');
        }

        $table->delete();

        return back()->with('success', 'Mesa eliminada.');
    }
}
