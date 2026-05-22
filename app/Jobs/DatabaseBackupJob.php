<?php

namespace App\Jobs;

use App\Models\DailyReportZ;
use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public DailyReportZ $report) {}

    public function handle(DatabaseBackupService $backups): void
    {
        try {
            $path = $backups->backup();
            $this->report->update(['database_backup_path' => $path]);
            Log::info("Backup de BD completado: {$path}");
        } catch (\Throwable $e) {
            Log::error('Backup de BD fallo: ' . $e->getMessage());
            throw $e;
        }
    }
}
