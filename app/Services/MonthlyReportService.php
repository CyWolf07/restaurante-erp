<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyReportService
{
    public function currentDashboard(): array
    {
        $period = $this->resolvePeriod((int) now()->year, (int) now()->month);
        $snapshot = $this->buildSnapshot($period['from'], $period['to']);
        $closedReport = MonthlyReport::where('period_year', $period['year'])
            ->where('period_month', $period['month'])
            ->first();

        return [
            'period' => $period,
            'snapshot' => $snapshot,
            'closed_report' => $closedReport,
            'recent_reports' => MonthlyReport::with('closer')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->take(6)
                ->get(),
            'reports_for_compare' => MonthlyReport::with('closer')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->take(24)
                ->get(),
        ];
    }

    public function closeMonth(User $admin, int $year, int $month): MonthlyReport
    {
        $period = $this->resolvePeriod($year, $month);
        if ($period['from']->copy()->endOfMonth()->greaterThanOrEqualTo(now())) {
            throw new \RuntimeException('Solo se pueden cerrar meses que ya terminaron.');
        }

        if (MonthlyReport::where('period_year', $year)->where('period_month', $month)->exists()) {
            throw new \RuntimeException('Este mes ya fue cerrado.');
        }

        $snapshot = $this->buildSnapshot($period['from'], $period['to']);

        $report = DB::transaction(function () use ($admin, $period, $snapshot) {
            $report = new MonthlyReport([
                'period_year'           => $period['year'],
                'period_month'          => $period['month'],
                'period_start'          => $period['from']->toDateString(),
                'period_end'            => $period['to']->toDateString(),
                'total_sales'           => $snapshot['totals']['sales'],
                'total_ingredient_cost' => $snapshot['totals']['ingredient_cost'],
                'gross_profit'          => $snapshot['totals']['gross_profit'],
                'total_orders_count'    => $snapshot['totals']['orders_count'],
                'total_products_sold'   => $snapshot['totals']['products_sold'],
                'closed_by'             => $admin->id,
            ]);

            $report->snapshot_data = $snapshot;
            $report->save();

            return $report->fresh('closer');
        });

        // The persisted snapshot remains available if rendering fails; opening the report retries the PDF.
        try {
            $this->regeneratePdf($report);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $report->fresh('closer');
    }

    public function regeneratePdf(MonthlyReport $report): string
    {
        $path = $this->generatePdf($report);
        $report->update(['pdf_local_path' => $path]);

        return $path;
    }

    public function buildSnapshot(Carbon $from, Carbon $to): array
    {
        $ordersBase = DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(inventory_confirmed_at, created_at)'), [$from, $to]);

        $totals = [
            'sales' => round((float) (clone $ordersBase)->sum('subtotal'), 2),
            'sales_with_tax' => round((float) (clone $ordersBase)->sum('total'), 2),
            'tax' => round((float) (clone $ordersBase)->sum('tax'), 2),
            'orders_count' => (int) (clone $ordersBase)->count(),
            'products_sold' => (int) DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.status', 'paid')
                ->whereBetween(DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'), [$from, $to])
                ->sum('order_details.quantity'),
        ];

        $products = $this->productRanking($from, $to);
        $supplies = $this->supplyRanking($from, $to);
        $tables = $this->tableRanking($from, $to);
        $days = $this->dayRanking($from, $to);
        $daysChronological = collect($days)->sortBy('date')->values()->toArray();
        $dayOfWeek = $this->dayOfWeekRanking($from, $to);
        $hourly = $this->hourlyRanking($from, $to);
        $categories = $this->categoryMarginRanking($products);

        $ingredientCost = round((float) collect($products)->sum('ingredient_cost'), 2);
        $totals['ingredient_cost'] = $ingredientCost;
        $totals['cost_incomplete'] = (clone $ordersBase)->whereNull('inventory_cost_captured_at')->exists()
            || collect($products)->contains(fn ($row) => $row['cost_incomplete']);
        $totals['gross_profit'] = round($totals['sales'] - $ingredientCost, 2);
        $totals['profit_margin'] = $totals['sales'] > 0
            ? round(($totals['gross_profit'] / $totals['sales']) * 100, 2)
            : 0.0;

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->translatedFormat('F Y'),
            ],
            'totals' => $totals,
            'products' => $products,
            'supplies' => $supplies,
            'tables' => $tables,
            'days' => $days,
            'days_chronological' => $daysChronological,
            'day_of_week_ranking' => $dayOfWeek,
            'hourly_ranking' => $hourly,
            'category_margins' => $categories,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function chartData(array $snapshot): array
    {
        $totals = $snapshot['totals'] ?? [];

        return [
            'profit_breakdown' => [
                'labels' => ['Costo insumos', 'Ganancia bruta'],
                'values' => [
                    round((float) ($totals['ingredient_cost'] ?? 0), 2),
                    round((float) ($totals['gross_profit'] ?? 0), 2),
                ],
            ],
            'products' => [
                'labels' => collect($snapshot['products'] ?? [])->take(10)->pluck('product')->values(),
                'values' => collect($snapshot['products'] ?? [])->take(10)->pluck('quantity')->values(),
            ],
            'supplies' => [
                'labels' => collect($snapshot['supplies'] ?? [])->take(10)->pluck('supply')->values(),
                'values' => collect($snapshot['supplies'] ?? [])->take(10)->pluck('quantity_used')->values(),
            ],
            'tables' => [
                'labels' => collect($snapshot['tables'] ?? [])->map(fn($row) => 'Mesa ' . $row['table_number'])->values(),
                'values' => collect($snapshot['tables'] ?? [])->pluck('sales')->values(),
            ],
            'day_of_week' => [
                'labels' => collect($snapshot['day_of_week_ranking'] ?? [])->pluck('day_name')->values(),
                'values' => collect($snapshot['day_of_week_ranking'] ?? [])->pluck('sales')->values(),
            ],
            'daily_sales' => [
                'labels' => collect($snapshot['days_chronological'] ?? $snapshot['days'] ?? [])->pluck('date_label')->values(),
                'values' => collect($snapshot['days_chronological'] ?? $snapshot['days'] ?? [])->pluck('sales')->values(),
            ],
            'hourly' => [
                'labels' => collect($snapshot['hourly_ranking'] ?? [])->pluck('hour_label')->values(),
                'values' => collect($snapshot['hourly_ranking'] ?? [])->pluck('sales')->values(),
            ],
        ];
    }

    public function getHistoricalComparison(array $reportIds): array
    {
        $reports = MonthlyReport::with('closer')
            ->whereIn('id', array_filter($reportIds))
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        return [
            'reports' => $reports->map(function (MonthlyReport $report) {
                $snapshot = $report->snapshot_data;

                return [
                    'id' => $report->id,
                    'label' => str_pad((string) $report->period_month, 2, '0', STR_PAD_LEFT) . '/' . $report->period_year,
                    'closed_by' => $report->closer?->name,
                    'totals' => $snapshot['totals'] ?? [],
                    'products' => $snapshot['products'] ?? [],
                    'supplies' => $snapshot['supplies'] ?? [],
                    'tables' => $snapshot['tables'] ?? [],
                    'day_of_week_ranking' => $snapshot['day_of_week_ranking'] ?? [],
                ];
            })->values(),
        ];
    }

    private function productRanking(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'), [$from, $to])
            ->select(
                'products.id',
                'products.name',
                'product_categories.name as category',
                DB::raw('SUM(order_details.quantity) as quantity'),
                DB::raw('SUM(order_details.subtotal) as sales')
            )
            ->groupBy('products.id', 'products.name', 'product_categories.name')
            ->orderByDesc(DB::raw('SUM(order_details.quantity)'))
            ->get();

        $costs = DB::table('inventory_logs as logs')
            ->join('order_details as details', 'details.id', '=', 'logs.order_detail_id')
            ->join('orders', 'orders.id', '=', 'details.order_id')
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'), [$from, $to])
            ->whereIn('logs.type', ['sale_confirmed', 'sale_consumption'])
            ->whereNull('logs.reversed_at')
            ->selectRaw('details.product_id, SUM(ABS(logs.quantity) * logs.unit_cost) as cost, SUM(CASE WHEN logs.unit_cost IS NULL THEN 1 ELSE 0 END) as missing')
            ->groupBy('details.product_id')->get()->keyBy('product_id');

        return $rows->map(function ($row) use ($costs) {
            $quantity = (int) $row->quantity;
            $cost = $costs->get($row->id);
            $ingredientCost = round((float) ($cost->cost ?? 0), 2);
            $sales = round((float) $row->sales, 2);

            return [
                'product_id' => $row->id,
                'product' => $row->name,
                'category' => $row->category ?? 'Sin categoria',
                'quantity' => $quantity,
                'sales' => $sales,
                'ingredient_cost' => $ingredientCost,
                'cost_incomplete' => (int) ($cost->missing ?? 0) > 0,
                'gross_profit' => round($sales - $ingredientCost, 2),
            ];
        })->values()->toArray();
    }

    private function supplyRanking(Carbon $from, Carbon $to): array
    {
        return DB::table('inventory_logs')
            ->join('supplies', 'supplies.id', '=', 'inventory_logs.supply_id')
            ->whereIn('inventory_logs.type', ['sale_confirmed', 'sale_consumption'])
            ->join('orders', 'orders.id', '=', 'inventory_logs.order_id')
            ->where('orders.status', 'paid')
            ->whereNull('inventory_logs.reversed_at')
            ->whereBetween(DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'), [$from, $to])
            ->select(
                'supplies.id',
                'supplies.name',
                'supplies.unit_type',
                DB::raw('SUM(ABS(inventory_logs.quantity) * inventory_logs.unit_cost) as historical_cost'),
                DB::raw('ABS(SUM(inventory_logs.quantity)) as quantity_used')
            )
            ->groupBy('supplies.id', 'supplies.name', 'supplies.unit_type')
            ->orderByDesc(DB::raw('ABS(SUM(inventory_logs.quantity))'))
            ->get()
            ->map(fn($row) => [
                'supply_id' => $row->id,
                'supply' => $row->name,
                'unit' => $this->unitLabel($row->unit_type),
                'quantity_used' => round((float) $row->quantity_used, 4),
                'estimated_cost' => round((float) $row->historical_cost, 2),
            ])
            ->toArray();
    }

    private function tableRanking(Carbon $from, Carbon $to): array
    {
        return DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(inventory_confirmed_at, created_at)'), [$from, $to])
            ->select(
                'table_number',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as sales'),
                DB::raw('SUM(total) as sales_with_tax')
            )
            ->groupBy('table_number')
            ->orderByDesc(DB::raw('SUM(subtotal)'))
            ->get()
            ->map(fn($row) => [
                'table_number' => (int) $row->table_number,
                'orders_count' => (int) $row->orders_count,
                'sales' => round((float) $row->sales, 2),
                'sales_with_tax' => round((float) $row->sales_with_tax, 2),
            ])
            ->toArray();
    }

    private function dayRanking(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(inventory_confirmed_at, created_at)'), [$from, $to])
            ->select(
                DB::raw('DATE(COALESCE(inventory_confirmed_at, created_at)) as sale_date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as sales')
            )
            ->groupBy(DB::raw('DATE(COALESCE(inventory_confirmed_at, created_at))'))
            ->orderByDesc(DB::raw('SUM(subtotal)'))
            ->get()
            ->map(fn($row) => [
                'date' => $row->sale_date,
                'date_label' => Carbon::parse($row->sale_date)->format('d/m'),
                'orders_count' => (int) $row->orders_count,
                'sales' => round((float) $row->sales, 2),
            ])
            ->toArray();

        return $rows;
    }

    private function dayOfWeekRanking(Carbon $from, Carbon $to): array
    {
        $salesByDay = DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(inventory_confirmed_at, created_at)'), [$from, $to])
            ->select(
                DB::raw('DATE(COALESCE(inventory_confirmed_at, created_at)) as sale_date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as sales')
            )
            ->groupBy(DB::raw('DATE(COALESCE(inventory_confirmed_at, created_at))'))
            ->get()
            ->groupBy(fn($row) => (int) Carbon::parse($row->sale_date)->dayOfWeekIso);

        $names = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'];

        return collect($names)->map(function (string $name, int $day) use ($salesByDay) {
            $rows = $salesByDay->get($day, collect());

            return [
                'day_number' => $day,
                'day_name' => $name,
                'orders_count' => (int) $rows->sum('orders_count'),
                'sales' => round((float) $rows->sum('sales'), 2),
            ];
        })->values()->toArray();
    }

    private function hourlyRanking(Carbon $from, Carbon $to): array
    {
        $hourExpression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', COALESCE(inventory_confirmed_at, created_at)) AS INTEGER)"
            : 'EXTRACT(HOUR FROM COALESCE(inventory_confirmed_at, created_at))';

        return DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(inventory_confirmed_at, created_at)'), [$from, $to])
            ->select(
                DB::raw("{$hourExpression} as sale_hour"),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as sales')
            )
            ->groupBy(DB::raw($hourExpression))
            ->orderByDesc(DB::raw('SUM(subtotal)'))
            ->get()
            ->map(fn($row) => [
                'hour' => (int) $row->sale_hour,
                'hour_label' => str_pad((string) $row->sale_hour, 2, '0', STR_PAD_LEFT) . ':00',
                'orders_count' => (int) $row->orders_count,
                'sales' => round((float) $row->sales, 2),
            ])
            ->toArray();
    }

    private function categoryMarginRanking(array $products): array
    {
        return collect($products)
            ->groupBy('category')
            ->map(function ($rows, string $category) {
                $sales = (float) $rows->sum('sales');
                $cost = (float) $rows->sum('ingredient_cost');
                $profit = $sales - $cost;

                return [
                    'category' => $category,
                    'quantity' => (int) $rows->sum('quantity'),
                    'sales' => round($sales, 2),
                    'ingredient_cost' => round($cost, 2),
                    'gross_profit' => round($profit, 2),
                    'profit_margin' => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('gross_profit')
            ->values()
            ->toArray();
    }

    private function generatePdf(MonthlyReport $report): string
    {
        $snapshot = $report->snapshot_data;
        $directory = storage_path("app/secure_reports/monthly/{$report->period_year}");

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "MONTHLY_{$report->period_year}_" . str_pad((string) $report->period_month, 2, '0', STR_PAD_LEFT) . "_{$report->created_at->format('YmdHis')}.pdf";
        $fullPath = "{$directory}/{$filename}";

        Pdf::loadView('reports.monthly-report-pdf', [
            'report' => $report->loadMissing('closer'),
            'snapshot' => $snapshot,
            'restaurant_name' => config('app.restaurant_name', 'Restaurante'),
        ])->setPaper('letter')->setOption('defaultFont', 'sans-serif')->save($fullPath);

        return $fullPath;
    }

    private function resolvePeriod(int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $from->copy()->endOfMonth();
        $to = $endOfMonth->isFuture() ? now() : $endOfMonth;

        return [
            'year' => $year,
            'month' => $month,
            'from' => $from,
            'to' => $to->copy()->endOfDay(),
        ];
    }

    private function unitLabel(string $unit): string
    {
        return match ($unit) {
            'gram' => 'g',
            'milliliter' => 'ml',
            'unit' => 'unidad(es)',
            default => $unit,
        };
    }
}
