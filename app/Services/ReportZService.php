<?php

namespace App\Services;

use App\Jobs\DatabaseBackupJob;
use App\Models\DailyReportZ;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportZService
{
    /**
     * Genera el Informe Z (Cierre Diario Fiscal).
     *
     * @throws \Exception Si existen órdenes activas sin cerrar
     */
    public function generateReportZ(User $cashier): DailyReportZ
    {
        return app(PosOperationService::class)->run(function () use ($cashier) {
            $today = Carbon::today();

            // 1. Verificar que no exista ya un cierre para este día
            if (DailyReportZ::whereDate('fiscal_date', $today)->exists()) {
                throw new \RuntimeException('Ya existe un Informe Z para la fecha de hoy: '.$today->toDateString());
            }

            // 2. Validación de Bloqueo: no deben existir órdenes activas
            $activeOrders = Order::active()->count();

            if ($activeOrders > 0) {
                throw new \RuntimeException(
                    "No se puede generar el Informe Z. Existen {$activeOrders} órdenes activas ".
                    '(pendientes, en cocina o listas). Todas deben estar pagadas o canceladas.'
                );
            }

            return DB::transaction(function () use ($cashier, $today) {
                // 3. Cálculos agregados del día
                $paidOrders = Order::paidOn($today);
                $cancelledOrders = Order::today()->where('status', 'cancelled');

                $totalSales = (float) $paidOrders->sum('total');
                $totalTax = (float) $paidOrders->sum('tax');
                $totalNet = (float) $paidOrders->sum('subtotal');
                $totalCount = $paidOrders->count();
                $cancelledCount = $cancelledOrders->count();
                $cancelledAmount = (float) $cancelledOrders->sum('total');

                // 4. Generar resumen detallado por categorías (JSONB)
                $summaryData = $this->buildSummaryData($today);

                // 5. Generar PDF del informe
                $pdfPath = $this->generatePdf([
                    'fiscal_date' => $today,
                    'total_sales' => $totalSales,
                    'total_tax' => $totalTax,
                    'total_net' => $totalNet,
                    'total_orders_count' => $totalCount,
                    'cancelled_orders_count' => $cancelledCount,
                    'total_cancelled_amount' => $cancelledAmount,
                    'cashier' => $cashier,
                    'summary_data' => $summaryData,
                    'restaurant_name' => config('app.restaurant_name', 'Restaurante'),
                ]);

                // 6. Crear registro inmutable en la BD
                $report = DailyReportZ::create([
                    'fiscal_date' => $today->toDateString(),
                    'total_sales' => $totalSales,
                    'total_tax' => $totalTax,
                    'total_net' => $totalNet,
                    'total_orders_count' => $totalCount,
                    'cancelled_orders_count' => $cancelledCount,
                    'total_cancelled_amount' => $cancelledAmount,
                    'cashier_id' => $cashier->id,
                    'pdf_local_path' => $pdfPath,
                    'summary_data' => $summaryData,
                ]);

                // 7. Bloquear todas las órdenes del día (inmutabilidad)
                Order::where(function ($query) use ($today) {
                    $query->paidOn($today)->orWhere(function ($cancelled) {
                        $cancelled->today()->where('status', 'cancelled');
                    });
                })
                    ->whereNull('locked_at')
                    ->update(['locked_at' => now()]);

                // 8. Despachar backup asíncrono de la BD
                DB::afterCommit(fn () => $this->dispatchBackup($report));

                Log::info('Informe Z generado exitosamente', [
                    'report_id' => $report->id,
                    'fiscal_date' => $today->toDateString(),
                    'total_sales' => $totalSales,
                    'cashier' => $cashier->name,
                ]);

                return $report;
            });
        }, requireOpen: false);
    }

    /**
     * Construye el resumen detallado de ventas por categoría para el JSONB.
     */
    private function buildSummaryData(Carbon $date): array
    {
        $orderDetails = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('COALESCE(orders.inventory_confirmed_at, orders.created_at)'), [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->select(
                'product_categories.name as category_name',
                'products.name as product_name',
                DB::raw('SUM(order_details.quantity) as total_quantity'),
                DB::raw('SUM(order_details.subtotal) as total_amount')
            )
            ->groupBy('product_categories.name', 'products.name')
            ->orderBy('product_categories.name')
            ->get();

        return [
            'by_product' => $orderDetails->map(fn ($row) => [
                'category' => $row->category_name ?? 'Sin Categoría',
                'product' => $row->product_name,
                'quantity' => (int) $row->total_quantity,
                'amount' => (float) $row->total_amount,
            ])->toArray(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Genera el PDF del Informe Z con diseño formal.
     */
    private function generatePdf(array $data): string
    {
        $year = $data['fiscal_date']->format('Y');
        $month = $data['fiscal_date']->format('m');
        $filename = "Z_{$data['fiscal_date']->format('Ymd')}_{$data['fiscal_date']->format('His')}.pdf";

        $directory = storage_path("app/secure_reports/{$year}/{$month}");
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fullPath = "{$directory}/{$filename}";

        $pdf = Pdf::loadView('reports.report-z-pdf', $data)
            ->setPaper('letter')
            ->setOption('defaultFont', 'sans-serif');

        $pdf->save($fullPath);

        return $fullPath;
    }

    /**
     * Despacha el job de backup de la BD de forma asíncrona.
     */
    private function dispatchBackup(DailyReportZ $report): void
    {
        try {
            DatabaseBackupJob::dispatch($report);
        } catch (\Throwable $e) {
            Log::warning('No se pudo encolar el backup de BD: '.$e->getMessage());
            // No lanzar excepción — el informe Z ya se creó exitosamente
        }
    }
}
