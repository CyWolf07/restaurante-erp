<?php

namespace App\Services;

use App\Models\CashierDailyClosure;
use App\Models\CashierCashCount;
use App\Models\DailyReportZ;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CashierDailyClosureService
{
    public function dailySummary(?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $paidOrders = Order::whereDate('created_at', $date)->where('status', 'paid');
        $cancelledOrders = Order::whereDate('created_at', $date)->where('status', 'cancelled');

        return [
            'fiscal_date' => $date,
            'total_sales' => (float) $paidOrders->sum('total'),
            'total_tax' => (float) $paidOrders->sum('tax'),
            'total_net' => (float) $paidOrders->sum('subtotal'),
            'total_orders_count' => $paidOrders->count(),
            'cancelled_orders_count' => $cancelledOrders->count(),
            'total_cancelled_amount' => (float) $cancelledOrders->sum('total'),
        ];
    }

    public function closeDay(User $cashier, array $expenses): CashierDailyClosure
    {
        $today = Carbon::today();

        if (CashierDailyClosure::where('fiscal_date', $today->toDateString())->exists()) {
            throw new \RuntimeException('Ya existe un cierre de caja para la fecha de hoy: ' . $today->toDateString());
        }

        $activeOrders = Order::whereDate('created_at', $today)
            ->whereIn('status', ['pending', 'in_kitchen', 'ready'])
            ->count();

        if ($activeOrders > 0) {
            throw new \RuntimeException("No se puede cerrar caja. Existen {$activeOrders} ordenes activas por terminar o cobrar.");
        }

        return DB::transaction(function () use ($cashier, $expenses, $today) {
            $summary = $this->dailySummary($today);
            $normalizedExpenses = $this->normalizeExpenses($expenses);
            $totalExpenses = array_sum(array_column($normalizedExpenses, 'amount'));
            $expectedCashTotal = $summary['total_sales'] - $totalExpenses;
            $cashCount = CashierCashCount::where('fiscal_date', $today->toDateString())->first();
            $declaredCashTotal = $cashCount ? (float) $cashCount->declared_cash_total : 0.0;
            $baseCashTotal = $cashCount ? (float) $cashCount->base_total : 0.0;
            $changeCashTotal = $cashCount ? (float) $cashCount->change_total : 0.0;
            $salesCashTotal = $cashCount ? (float) $cashCount->sales_total : 0.0;
            $cashDifference = $salesCashTotal - $expectedCashTotal;
            $reportZ = DailyReportZ::where('fiscal_date', $today->toDateString())->first();
            $reportZTotal = $reportZ ? (float) $reportZ->total_sales : null;
            $differenceVsReportZ = $reportZTotal === null ? null : $summary['total_sales'] - $reportZTotal;

            $closure = CashierDailyClosure::create([
                ...$summary,
                'fiscal_date' => $today->toDateString(),
                'cashier_id' => $cashier->id,
                'closed_at' => now(),
                'expenses' => $normalizedExpenses,
                'cash_count_summary' => $cashCount ? [
                    'base' => $cashCount->base_counts ?? [],
                    'change' => $cashCount->change_counts ?? [],
                    'sales' => $cashCount->sales_counts ?? [],
                    'notes' => $cashCount->notes,
                ] : null,
                'base_cash_total' => $baseCashTotal,
                'change_cash_total' => $changeCashTotal,
                'sales_cash_total' => $salesCashTotal,
                'declared_cash_total' => $declaredCashTotal,
                'cash_difference' => $cashDifference,
                'total_expenses' => $totalExpenses,
                'expected_cash_total' => $expectedCashTotal,
                'report_z_total_sales' => $reportZTotal,
                'difference_vs_report_z' => $differenceVsReportZ,
            ]);

            $closure->update([
                'pdf_local_path' => $this->generatePdf($closure->fresh('cashier')),
            ]);

            return $closure->fresh('cashier');
        });
    }

    private function normalizeExpenses(array $expenses): array
    {
        return collect($expenses)
            ->map(fn(array $expense) => [
                'concept' => trim((string) ($expense['concept'] ?? '')),
                'amount' => round((float) ($expense['amount'] ?? 0), 2),
            ])
            ->filter(fn(array $expense) => $expense['concept'] !== '' && $expense['amount'] > 0)
            ->values()
            ->all();
    }

    private function generatePdf(CashierDailyClosure $closure): string
    {
        $year = $closure->fiscal_date->format('Y');
        $month = $closure->fiscal_date->format('m');
        $filename = "CIERRE_CAJA_{$closure->fiscal_date->format('Ymd')}_{$closure->closed_at->format('His')}.pdf";

        $directory = storage_path("app/cashier_closures/{$year}/{$month}");
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fullPath = "{$directory}/{$filename}";

        Pdf::loadView('reports.cashier-daily-closure-pdf', [
            'closure' => $closure,
            'restaurant_name' => 'Sistema Contable El Muelle Restaurante',
        ])
            ->setPaper([0, 0, 226.77, 841.89])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->save($fullPath);

        return $fullPath;
    }
}
