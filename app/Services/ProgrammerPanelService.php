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
     * Purga datos temporales: caché, logs de sesión Laravel, archivos temporales.
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

        // Limpiar logs de Laravel
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            $size = File::size($logPath);
            File::put($logPath, '');
            $results[] = "Log principal truncado ({$this->formatBytes($size)} liberados)";
        }

        // Limpiar archivos de sesiones (si usa driver 'file')
        $sessionPath = storage_path('framework/sessions');
        if (File::isDirectory($sessionPath)) {
            $count = count(File::files($sessionPath));
            File::cleanDirectory($sessionPath);
            $results[] = "{$count} archivos de sesión eliminados";
        }

        return $results;
    }

    /**
     * Limpia la cola de jobs y mata workers atascados.
     */
    public function killStalledProcesses(): array
    {
        $results = [];

        // Limpiar cola de trabajos pendientes
        try {
            Artisan::call('queue:clear');
            $results[] = 'Cola de trabajos limpiada';
        } catch (\Throwable $e) {
            $results[] = 'Error limpiando cola: ' . $e->getMessage();
        }

        // Reiniciar workers
        try {
            Artisan::call('queue:restart');
            $results[] = 'Workers de cola reiniciados';
        } catch (\Throwable $e) {
            $results[] = 'Error reiniciando workers: ' . $e->getMessage();
        }

        // Limpiar jobs fallidos
        try {
            $failedCount = DB::table('failed_jobs')->count();
            Artisan::call('queue:flush');
            $results[] = "{$failedCount} jobs fallidos eliminados";
        } catch (\Throwable $e) {
            $results[] = 'Error limpiando jobs fallidos: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Recalcula el stock de todos los insumos desde cero sumando inventory_logs.
     * Si hay desfase, aplica un parche de ajuste automático.
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

            // Si hay desfase significativo, aplicar parche
            if (abs($diff) > 0.0001 && !$dryRun) {
                DB::transaction(function () use ($supply, $diff, $calculatedStock, $userId) {
                    // Corregir el stock actual al valor calculado
                    $supply->update(['current_stock' => $calculatedStock]);

                    // Registrar el ajuste como 'programmer_adjustment'
                    // Nota: el ajuste NO cambia el cálculo, solo corrige current_stock
                    // No agregamos un log extra porque eso cambiaría el cálculo
                    // Solo si queremos documentar la discrepancia:
                    if ($diff != 0) {
                        InventoryLog::create([
                            'supply_id'   => $supply->id,
                            'type'        => 'programmer_adjustment',
                            'quantity'    => $diff,
                            'stock_after' => $calculatedStock,
                            'user_id'     => $userId,
                            'description' => "Parche de integridad: desfase de {$diff} detectado y corregido. " .
                                             "Stock anterior: {$supply->current_stock}, Calculado: {$calculatedStock}",
                            'created_at'  => now(),
                        ]);
                    }
                });

                $entry['repaired'] = true;
                Log::warning("Integridad reparada para {$supply->name}: desfase de {$diff}");
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
