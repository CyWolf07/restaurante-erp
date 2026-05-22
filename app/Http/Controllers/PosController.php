<?php

namespace App\Http\Controllers;

use App\Jobs\PrintReceiptJob;
use App\Jobs\PrintKitchenTicketJob;
use App\Models\CashierDailyClosure;
use App\Models\CashierCashCount;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RestaurantTable;
use App\Services\CashierDailyClosureService;
use App\Services\InventoryEngine;
use App\Services\ReportZService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function pos(CashierDailyClosureService $cashierClosures)
    {
        $tables = RestaurantTable::active()->ordered()->get()->map(function (RestaurantTable $table) {
            $order = $table->activeOrder();

            return [
                'id'     => $table->id,
                'number' => $table->number,
                'name'   => $table->display_name,
                'zone'   => $table->zone,
                'order'  => $order,
                'status' => $order ? $order->status : 'free',
                'color'  => $table->status_color,
            ];
        });

        $cashierClosureSummary = $cashierClosures->dailySummary();
        $cashierClosure = CashierDailyClosure::where('fiscal_date', today()->toDateString())->first();
        $cashierClosureExists = $cashierClosure !== null;

        return view('cashier.pos', compact('tables', 'cashierClosureSummary', 'cashierClosure', 'cashierClosureExists'));
    }

    public function tableDetail(int $table)
    {
        $restaurantTable = RestaurantTable::where('number', $table)->firstOrFail();
        $order = Order::forTable($table)
            ->with(['details.product', 'details.modifiers.modifier', 'waiter'])
            ->latest()
            ->first();

        return view('cashier.table-detail', compact('order', 'table', 'restaurantTable'));
    }

    public function markReady(Order $order)
    {
        if (!$order->isInKitchen()) {
            return back()->with('error', 'La orden no está en cocina.');
        }
        $order->update(['status' => 'ready']);
        return back()->with('success', 'Orden marcada como lista.');
    }

    public function sendToKitchen(Order $order)
    {
        if (!$order->isPending()) {
            return back()->with('error', 'Esta orden ya no esta pendiente.');
        }

        $order->update([
            'status' => 'in_kitchen',
            'kitchen_sent_by' => Auth::id(),
            'kitchen_sent_at' => now(),
        ]);

        PrintKitchenTicketJob::dispatchSync($order->fresh(['waiter', 'kitchenSentBy', 'restaurantTable']));

        return back()->with('success', "Orden Mesa #{$order->table_number} enviada a cocina e impresa.");
    }

    public function destroyOrderDetail(OrderDetail $detail, InventoryEngine $engine)
    {
        $order = $detail->order()->with('details')->firstOrFail();

        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede eliminar un plato de una orden cerrada.');
        }

        if (!in_array(Auth::user()->role, ['cashier', 'administrator', 'programmer'], true)) {
            abort(403);
        }

        DB::transaction(function () use ($detail, $engine, $order) {
            $engine->reverseReservationForDetail($detail->load('product'));
            $detail->delete();

            if ($order->details()->count() === 0) {
                $order->update([
                    'status' => 'cancelled',
                    'cashier_id' => Auth::id(),
                    'cancellation_reason' => 'Orden sin platos despues de eliminacion en caja.',
                ]);
                return;
            }

            $order->refresh()->recalculateTotals();
        });

        return redirect()->route('cashier.table-detail', $order->table_number)
            ->with('success', 'Plato eliminado y totales recalculados.');
    }

    public function payOrder(Request $request, Order $order, InventoryEngine $engine)
    {
        if ($order->isPaid() || $order->isCancelled()) {
            return back()->with('error', 'Esta orden ya fue procesada.');
        }

        $order->update(['status' => 'paid', 'cashier_id' => Auth::id()]);
        $engine->confirmForOrder($order->fresh());

        if ($request->boolean('print_receipt', true)) {
            PrintReceiptJob::dispatchSync($order->fresh());
        }

        return redirect()->route('cashier.pos')
            ->with('success', 'Orden Mesa #' . $order->table_number . ' cobrada: ' . cop($order->total));
    }

    public function cancelOrder(Request $request, Order $order, InventoryEngine $engine)
    {
        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede cancelar esta orden.');
        }
        $request->validate(['reason' => 'required|string|min:5']);
        $engine->reverseReservation($order);
        $order->update([
            'status' => 'cancelled',
            'cashier_id' => Auth::id(),
            'cancellation_reason' => $request->reason,
        ]);
        return redirect()->route('cashier.pos')
            ->with('success', "Orden Mesa #{$order->table_number} cancelada. Inventario revertido.");
    }

    public function generateReportZ(ReportZService $service)
    {
        try {
            $report = $service->generateReportZ(Auth::user());
            return redirect()->route('cashier.pos')
                ->with('success', "Informe Z generado para {$report->fiscal_date->format('d/m/Y')}.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function closeCashierDay(Request $request, CashierDailyClosureService $service)
    {
        $data = $request->validate([
            'expenses' => 'nullable|array',
            'expenses.*.concept' => 'nullable|string|max:120',
            'expenses.*.amount' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        try {
            $closure = $service->closeDay(Auth::user(), $data['expenses'] ?? []);

            return redirect()->route('cashier.cash-closure.pdf', $closure)
                ->with('success', 'Cierre de caja generado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cashierClosurePdf(CashierDailyClosure $closure)
    {
        if (!$closure->pdf_local_path || !file_exists($closure->pdf_local_path)) {
            abort(404, 'El PDF del cierre de caja no existe en disco.');
        }

        return response()->file($closure->pdf_local_path);
    }

    public function cashCount()
    {
        $denominations = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50];
        $cashCount = CashierCashCount::where('fiscal_date', today()->toDateString())->first();

        return view('cashier.cash-count', compact('denominations', 'cashCount'));
    }

    public function storeCashCount(Request $request)
    {
        $data = $request->validate([
            'base' => 'nullable|array',
            'base.*' => 'nullable|integer|min:0|max:99999',
            'change' => 'nullable|array',
            'change.*' => 'nullable|integer|min:0|max:99999',
            'sales' => 'nullable|array',
            'sales.*' => 'nullable|integer|min:0|max:99999',
            'notes' => 'nullable|string|max:1000',
        ]);

        $base = $this->normalizeCashCounts($data['base'] ?? []);
        $change = $this->normalizeCashCounts($data['change'] ?? []);
        $sales = $this->normalizeCashCounts($data['sales'] ?? []);

        $baseTotal = $this->cashCountTotal($base);
        $changeTotal = $this->cashCountTotal($change);
        $salesTotal = $this->cashCountTotal($sales);

        CashierCashCount::updateOrCreate(
            ['fiscal_date' => today()->toDateString()],
            [
                'cashier_id' => Auth::id(),
                'base_counts' => $base,
                'change_counts' => $change,
                'sales_counts' => $sales,
                'base_total' => $baseTotal,
                'change_total' => $changeTotal,
                'sales_total' => $salesTotal,
                'declared_cash_total' => $baseTotal + $changeTotal + $salesTotal,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Arqueo de caja guardado correctamente.');
    }

    private function normalizeCashCounts(array $counts): array
    {
        $normalized = [];

        foreach ($counts as $denomination => $quantity) {
            $denomination = (int) $denomination;
            $quantity = (int) ($quantity ?? 0);

            if ($denomination > 0 && $quantity >= 0) {
                $normalized[(string) $denomination] = $quantity;
            }
        }

        krsort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private function cashCountTotal(array $counts): float
    {
        return array_reduce(
            array_keys($counts),
            fn(float $total, string $denomination) => $total + ((int) $denomination * (int) $counts[$denomination]),
            0.0
        );
    }
}
