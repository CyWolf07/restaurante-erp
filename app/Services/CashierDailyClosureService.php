<?php

namespace App\Services;

use App\Models\CashierCashCount;
use App\Models\CashierDailyClosure;
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

        $paidOrders = Order::paidOn($date);
        $cancelledOrders = Order::whereDate('created_at', $date)->where('status', 'cancelled');
        $fiscalPending = app(FiscalDocumentService::class)->unsentSummary();

        return [
            'fiscal_date' => $date,
            'total_sales' => (float) $paidOrders->sum('total'),
            'cash_sales' => (float) (clone $paidOrders)->get()->sum(fn ($order) => (float) ($order->payment_breakdown['cash'] ?? 0)),
            'unclassified_payments_total' => (float) (clone $paidOrders)->whereNull('payment_breakdown')->sum('total'),
            'total_tax' => (float) $paidOrders->sum('tax'),
            'total_net' => (float) $paidOrders->sum('subtotal'),
            'total_orders_count' => $paidOrders->count(),
            'cancelled_orders_count' => $cancelledOrders->count(),
            'total_cancelled_amount' => (float) $cancelledOrders->sum('total'),
            'fiscal_pending_count' => $fiscalPending['count'],
            'fiscal_pending_total' => $fiscalPending['total'],
        ];
    }

    public function closeDay(User $cashier, array $expenses): CashierDailyClosure
    {
        return app(PosOperationService::class)->run(function () use ($cashier, $expenses) {
            $today = Carbon::today();

            if (CashierDailyClosure::whereDate('fiscal_date', $today)->exists()) {
                throw new \RuntimeException('Ya existe un cierre de caja para la fecha de hoy: '.$today->toDateString());
            }

            $activeOrders = Order::active()->count();

            if ($activeOrders > 0) {
                throw new \RuntimeException("No se puede cerrar caja. Existen {$activeOrders} ordenes activas por terminar o cobrar.");
            }

            if (! CashierCashCount::whereDate('fiscal_date', $today)->exists()) {
                throw new \RuntimeException('Registra el arqueo de caja antes de cerrar el día.');
            }

            return DB::transaction(function () use ($cashier, $expenses, $today) {
                $summary = $this->dailySummary($today);
                if ($summary['unclassified_payments_total'] > 0) {
                    throw new \RuntimeException('Hay cobros históricos sin medio de pago identificado. Deben conciliarse antes del cierre: '.cop($summary['unclassified_payments_total']));
                }
                $normalizedExpenses = $this->normalizeExpenses($expenses);
                $totalExpenses = array_sum(array_column($normalizedExpenses, 'amount'));
                $expectedCashTotal = $summary['cash_sales'] - $totalExpenses;
                $cashSales = $summary['cash_sales'];
                unset($summary['cash_sales'], $summary['unclassified_payments_total']);
                $cashCount = CashierCashCount::whereDate('fiscal_date', $today)->first();
                $declaredCashTotal = $cashCount ? (float) $cashCount->declared_cash_total : 0.0;
                $baseCashTotal = $cashCount ? (float) $cashCount->base_total : 0.0;
                $changeCashTotal = $cashCount ? (float) $cashCount->change_total : 0.0;
                $salesCashTotal = $cashCount ? (float) $cashCount->sales_total : 0.0;
                $cashDifference = $salesCashTotal - $expectedCashTotal;
                $reportZ = DailyReportZ::whereDate('fiscal_date', $today)->first();
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
                        'cash_sales' => $cashSales,
                        'non_cash_sales' => $summary['total_sales'] - $cashSales,
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
        }, requireOpen: false);
    }

    private function normalizeExpenses(array $expenses): array
    {
        return collect($expenses)
            ->map(fn (array $expense) => [
                'concept' => trim((string) ($expense['concept'] ?? '')),
                'amount' => round((float) ($expense['amount'] ?? 0), 2),
            ])
            ->filter(fn (array $expense) => $expense['concept'] !== '' && $expense['amount'] > 0)
            ->values()
            ->all();
    }

    public function generatePdf(CashierDailyClosure $closure): string
    {
        $year = $closure->fiscal_date->format('Y');
        $month = $closure->fiscal_date->format('m');
        $filename = "CIERRE_CAJA_{$closure->fiscal_date->format('Ymd')}_{$closure->closed_at->format('His')}.pdf";

        $directory = storage_path("app/cashier_closures/{$year}/{$month}");
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fullPath = "{$directory}/{$filename}";

        Pdf::loadView('reports.cashier-daily-closure-pdf', [
            'closure' => $closure,
            'restaurant_name' => config('app.restaurant_name', 'Restaurante'),
        ])
            ->setPaper([0, 0, 226.77, 841.89])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->save($fullPath);

        return $fullPath;
    }
}
