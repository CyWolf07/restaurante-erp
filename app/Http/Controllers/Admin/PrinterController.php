<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Services\PrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrinterController extends Controller
{
    public function index(PrinterService $printerService)
    {
        $printers = Printer::orderBy('purpose')->orderBy('name')->get();
        $windowsPrinters = $printerService->listWindowsPrinters();
        $tables = RestaurantTable::active()->ordered()->get();

        return view('admin.printers.index', compact('printers', 'windowsPrinters', 'tables'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePrinter($request);

        DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                Printer::where('purpose', $data['purpose'])->update(['is_default' => false]);
            }
            Printer::create($data);
        });

        return back()->with('success', 'Impresora registrada.');
    }

    public function update(Request $request, Printer $printer)
    {
        $data = $this->validatePrinter($request);

        DB::transaction(function () use ($data, $printer) {
            if ($data['is_default'] ?? false) {
                Printer::where('purpose', $data['purpose'])->where('id', '!=', $printer->id)
                    ->update(['is_default' => false]);
            }
            $printer->update($data);
        });

        return back()->with('success', 'Impresora actualizada.');
    }

    public function destroy(Printer $printer)
    {
        RestaurantTable::where('kitchen_printer_id', $printer->id)->update(['kitchen_printer_id' => null]);
        RestaurantTable::where('receipt_printer_id', $printer->id)->update(['receipt_printer_id' => null]);
        $printer->delete();

        return back()->with('success', 'Impresora eliminada.');
    }

    public function test(Printer $printer, PrinterService $service)
    {
        $result = $service->testConnection($printer);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    private function validatePrinter(Request $request): array
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'purpose'          => 'required|in:kitchen,receipt,bar',
            'connection_type'  => 'required|in:network,windows',
            'address'          => 'required|string|max:255',
            'port'             => 'nullable|integer|min:1|max:65535',
            'paper_width'      => 'nullable|in:58,80',
            'notes'            => 'nullable|string',
        ]);

        $data['port'] = $data['port'] ?? 9100;
        $data['paper_width'] = $data['paper_width'] ?? 80;
        $data['is_default'] = $request->boolean('is_default');
        $data['active'] = $request->boolean('active', true);

        return $data;
    }
}
