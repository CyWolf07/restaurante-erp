<?php

namespace App\Console\Commands;

use App\Services\ProgrammerPanelService;
use Illuminate\Console\Command;

class RepairInventoryIntegrity extends Command
{
    protected $signature = 'inventory:repair {--dry-run : Solo mostrar desfases sin corregir} {--user-id= : ID del programador}';
    protected $description = 'Recalcula stock desde inventory_logs y corrige desfases de integridad';

    public function handle(ProgrammerPanelService $service): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if (!$userId && !$dryRun) {
            $this->error('Se requiere --user-id para registrar los ajustes (o usar --dry-run)');
            return self::FAILURE;
        }

        $this->info($dryRun ? '🔍 Modo diagnóstico (dry-run)...' : '🔧 Reparando integridad...');

        $results = $service->repairInventoryIntegrity($userId ?? 'system', $dryRun);

        $needsRepair = collect($results)->where('needs_repair', true);

        if ($needsRepair->isEmpty()) {
            $this->info('✅ Todos los insumos tienen stock consistente.');
            return self::SUCCESS;
        }

        $this->table(
            ['Insumo', 'Stock Actual', 'Calculado', 'Desfase', 'Reparado'],
            $needsRepair->map(fn($r) => [
                $r['supply_name'], $r['current_stock'], $r['calculated_stock'],
                $r['difference'], $r['repaired'] ? '✅' : '⏸️ (dry-run)',
            ])->toArray()
        );

        $this->warn("Se encontraron {$needsRepair->count()} insumos con desfase.");
        return self::SUCCESS;
    }
}
