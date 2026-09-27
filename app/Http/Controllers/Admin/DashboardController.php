<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyReportZ;
use App\Models\MonthlyReport;
use App\Models\PhysicalInventory;
use App\Models\Supply;
use App\Services\AnalyticsService;
use App\Services\InventoryEngine;
use App\Services\MonthlyReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function dashboard(AnalyticsService $analytics, MonthlyReportService $monthlyReports)
    {
        $comparison = $analytics->getConsumptionComparison();
        $lowStockSupplies = Supply::where('active', true)
            ->whereColumn('current_stock', '<=', 'min_stock')->get();
        $recentInventories = PhysicalInventory::with('admin')->latest('recorded_at')->take(5)->get();
        $notifications = cache()->get('low_stock_notifications', []);
        $monthly = $monthlyReports->currentDashboard();
        $chartData = $monthlyReports->chartData($monthly['snapshot'] ?? []);
        $allProducts = $monthly['snapshot']['products'] ?? [];
        $allSupplies = $monthly['snapshot']['supplies'] ?? [];
        $allTables = $monthly['snapshot']['tables'] ?? [];
        $allDays = $monthly['snapshot']['days'] ?? [];

        return view('admin.dashboard', compact(
            'comparison',
            'lowStockSupplies',
            'recentInventories',
            'notifications',
            'monthly',
            'chartData',
            'allProducts',
            'allSupplies',
            'allTables',
            'allDays'
        ));
    }

    public function closeMonthlyReport(Request $request, MonthlyReportService $monthlyReports)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        try {
            $report = $monthlyReports->closeMonth(Auth::user(), (int) $data['year'], (int) $data['month']);

            if (! $report->pdf_local_path) {
                return back()->with('warning', 'El cierre mensual quedó guardado. No se pudo generar el PDF; abre el informe desde el historial para reintentarlo.');
            }

            return redirect()->route('admin.monthly-reports.pdf', $report)
                ->with('success', 'Cierre mensual generado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function monthlyReportPdf(MonthlyReport $report, MonthlyReportService $monthlyReports)
    {
        $path = $report->pdf_local_path;

        if (!$path || !file_exists($path)) {
            $path = $monthlyReports->regeneratePdf($report);
        }

        return response()->file($path);
    }

    public function dailyReportPdf(DailyReportZ $report)
    {
        if (!$report->pdf_local_path || !file_exists($report->pdf_local_path)) {
            abort(404, 'El PDF del Informe Z no existe en disco.');
        }

        return response()->file($report->pdf_local_path);
    }

    public function compareReports(Request $request, MonthlyReportService $monthlyReports)
    {
        $data = $request->validate([
            'reports' => 'required|array|min:1|max:4',
            'reports.*' => 'required|string',
        ]);

        return response()->json($monthlyReports->getHistoricalComparison($data['reports']));
    }

    public function supplies()
    {
        $supplies = Supply::where('active', true)->orderBy('name')->get();
        return view('admin.supplies', compact('supplies'));
    }

    public function storeSupply(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit_type' => 'required|in:gram,milliliter,unit',
            'current_stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
        ]);
        app(\App\Services\InventoryCatalogService::class)->create($data, $request->user()->id);
        return back()->with('success', 'Insumo creado correctamente.');
    }

    public function registerPurchase(Request $request, Supply $supply, InventoryEngine $engine)
    {
        $request->validate(['quantity' => 'required|numeric|decimal:0,4|min:0.0001|max:99999999.9999', 'unit_value' => 'required|numeric|decimal:0,2|min:0|max:99999999.99', 'description' => 'nullable|string|max:1000', 'operation_key' => 'required|uuid']);
        $engine->registerPurchase($supply, $request->quantity, Auth::id(), $request->description, $request->operation_key, (string) $request->unit_value);
        return back()->with('success', "Compra de {$request->quantity} {$supply->unit_label} registrada.");
    }

    public function registerWaste(Request $request, Supply $supply, InventoryEngine $engine)
    {
        $request->validate(['quantity' => 'required|numeric|min:0.0001', 'reason' => 'required|string|min:3', 'operation_key' => 'required|uuid']);
        $engine->registerWaste($supply, $request->quantity, Auth::id(), $request->reason, $request->operation_key);
        return back()->with('success', "Merma de {$request->quantity} {$supply->unit_label} registrada.");
    }

    public function createPhysicalInventory()
    {
        $supplies = Supply::where('active', true)->orderBy('name')->get();
        return view('admin.physical-inventory', compact('supplies'));
    }

    public function storePhysicalInventory(Request $request, AnalyticsService $analytics)
    {
        $request->validate([
            'counts'   => 'required|array',
            'counts.*' => 'required|numeric|min:0',
            'notes'    => 'nullable|string',
        ]);

        $inventory = $analytics->analyzePhysicalInventory(
            admin: Auth::user(),
            physicalCounts: $request->counts,
            notes: $request->notes
        );

        return redirect()->route('admin.inventory-results', $inventory)
            ->with('success', 'Conteo físico procesado con análisis de incongruencias.');
    }

    public function inventoryResults(PhysicalInventory $inventory, AnalyticsService $analytics)
    {
        $dashboard = $analytics->getInconsistencyDashboard($inventory);
        $inventory->load('admin');
        return view('admin.inventory-results', compact('inventory', 'dashboard'));
    }
}
