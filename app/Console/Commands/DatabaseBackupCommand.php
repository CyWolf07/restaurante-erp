<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Genera un backup de la base de datos configurada';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $path = $backups->backup();
            $this->info("Backup completado: {$path}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup fallo: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
