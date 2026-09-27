<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Supply;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProgrammerPanelService
{
    /**
     * Limpia cachés sin eliminar sesiones ni registros de diagnóstico.
     */
    public function purgeTemporaryData(): array
    {
        $results = [];

        // Limpiar caché de la aplicación
        Artisan::call('cache:clear');
        $results[] = 'Cache de aplicación limpiada';

        // Limpiar caché de vistas compiladas
        Artisan::call('view:clear');
        $results[] = 'Vistas compiladas limpiadas';

        // Limpiar caché de rutas
        try {
            Artisan::call('route:clear');
            $results[] = 'Cache de rutas limpiada';
        } catch (\Throwable $e) {
            $results[] = 'Cache de rutas: ' . $e->getMessage();
        }

        $results[] = 'Sesiones y registros de diagnóstico conservados';

        return $results;
    }

    /**
     * Solicita un reinicio ordenado conservando todos los trabajos.
     */
    public function killStalledProcesses(): array
    {
        $results = [];

        // Reiniciar workers
        try {
            Artisan::call('queue:restart');
            $results[] = 'Workers de cola reiniciados';
        } catch (\Throwable $e) {
            $results[] = 'Error reiniciando workers: ' . $e->getMessage();
        }

        $results[] = 'Trabajos pendientes y fallidos conservados para seguimiento';

        return $results;
    }

    /**
     * Compara saldos y movimientos sin inventar saldos iniciales históricos.
     * Se conserva la firma anterior para los clientes de diagnóstico.
     *
     * @return array Resultado de la reparación por insumo
     */
    public function repairInventoryIntegrity(string $userId, bool $dryRun = false): array
    {
        $results = [];
        $supplies = Supply::all();

        foreach ($supplies as $supply) {
            // Recalcular stock sumando TODOS los inventory_logs históricamente
            $calculatedStock = (float) InventoryLog::where('supply_id', $supply->id)->sum('quantity');
            $currentStock    = (float) $supply->current_stock;
            $diff            = round($calculatedStock - $currentStock, 4);

            $entry = [
                'supply_id'        => $supply->id,
                'supply_name'      => $supply->name,
                'current_stock'    => $currentStock,
                'calculated_stock' => $calculatedStock,
                'difference'       => $diff,
                'needs_repair'     => abs($diff) > 0.0001,
                'repaired'         => false,
            ];

            // A missing opening balance is not evidence that the operational stock is wrong.
            // Keep historical records intact; reconciliation requires an explicit reviewed adjustment.
            if (abs($diff) > 0.0001) {
                $entry['review_required'] = true;
                $entry['reason'] = 'Revisar saldo inicial y documentos. No se aplican parches automáticos.';
            }

            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Obtiene métricas del sistema para el panel del programador.
     */
    public function getSystemMetrics(): array
    {
        $storagePath = storage_path();

        return [
            'disk_free'       => disk_free_space(base_path()),
            'disk_total'      => disk_total_space(base_path()),
            'disk_used_pct'   => round((1 - disk_free_space(base_path()) / disk_total_space(base_path())) * 100, 1),
            'log_size'        => File::exists(storage_path('logs/laravel.log'))
                                 ? File::size(storage_path('logs/laravel.log')) : 0,
            'session_count'   => File::isDirectory(storage_path('framework/sessions'))
                                 ? count(File::files(storage_path('framework/sessions'))) : 0,
            'pending_jobs'    => DB::table('jobs')->count(),
            'failed_jobs'     => DB::table('failed_jobs')->count(),
            'total_supplies'  => Supply::count(),
            'low_stock_count' => Supply::whereColumn('current_stock', '<=', 'min_stock')->count(),
            'total_logs'      => InventoryLog::count(),
            'backups_count'   => File::isDirectory(storage_path('app/backups'))
                                 ? count(File::files(storage_path('app/backups'))) : 0,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
