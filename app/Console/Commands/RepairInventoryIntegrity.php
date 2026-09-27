<?php

namespace App\Console\Commands;

use App\Services\ProgrammerPanelService;
use Illuminate\Console\Command;

class RepairInventoryIntegrity extends Command
{
    protected $signature = 'inventory:repair {--dry-run : Solo mostrar desfases sin corregir} {--user-id= : ID del programador}';
    protected $description = 'Compara saldos y movimientos sin modificar existencias';

    public function handle(ProgrammerPanelService $service): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        $this->info('Diagnóstico de integridad; no se aplicarán ajustes automáticos.');

        $results = $service->repairInventoryIntegrity($userId ?? 'system', $dryRun);

        $needsRepair = collect($results)->where('needs_repair', true);

        if ($needsRepair->isEmpty()) {
            $this->info('✅ Todos los insumos tienen stock consistente.');
            return self::SUCCESS;
        }

        $this->table(
            ['Insumo', 'Stock Actual', 'Calculado', 'Desfase', 'Estado'],
            $needsRepair->map(fn($r) => [
                $r['supply_name'], $r['current_stock'], $r['calculated_stock'],
                $r['difference'], 'Revisar documentos',
            ])->toArray()
        );

        $this->warn("Se encontraron {$needsRepair->count()} insumos con desfase.");
        return self::SUCCESS;
    }
}
