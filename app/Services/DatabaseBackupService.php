<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function backup(): string
    {
        $driver = config('database.default');
        $directory = storage_path('app/backups');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return match ($driver) {
            'sqlite' => $this->backupSqlite($directory),
            'pgsql' => $this->backupPostgres($directory),
            default => throw new \RuntimeException("Backup no configurado para DB_CONNECTION={$driver}."),
        };
    }

    private function backupSqlite(string $directory): string
    {
        $database = config('database.connections.sqlite.database');

        if (!$database || !File::exists($database)) {
            throw new \RuntimeException('No existe el archivo SQLite para respaldar.');
        }

        $path = $directory . '/sqlite_' . now()->format('Ymd_His') . '.sqlite';
        File::copy($database, $path);

        return $path;
    }

    private function backupPostgres(string $directory): string
    {
        $path = $directory . '/pgsql_' . now()->format('Ymd_His') . '.dump';
        $connection = config('database.connections.pgsql');
        $pgdump = $this->resolvePgDumpPath();

        $process = new Process([
            $pgdump,
            '-U', $connection['username'],
            '-h', $connection['host'],
            '-p', (string) ($connection['port'] ?? '5432'),
            '-F', 'c',
            '-f', $path,
            $connection['database'],
        ]);

        $process->setEnv(['PGPASSWORD' => $connection['password']]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput() ?: 'pg_dump fallo sin salida de error.');
        }

        return $path;
    }

    private function resolvePgDumpPath(): string
    {
        $configured = (string) config('app.pgdump_path', 'pg_dump');

        if ($configured !== 'pg_dump' && File::exists($configured)) {
            return $configured;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return $configured;
        }

        $major = $this->postgresServerMajor();
        $candidates = [];

        if ($major) {
            $candidates[] = "C:\\Program Files\\PostgreSQL\\{$major}\\bin\\pg_dump.exe";
            $candidates[] = "C:\\Program Files\\PostgreSQL\\{$major}\\pgAdmin 4\\runtime\\pg_dump.exe";
        }

        $candidates = array_merge($candidates, glob('C:\\Program Files\\PostgreSQL\\*\\bin\\pg_dump.exe') ?: []);

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $configured;
    }

    private function postgresServerMajor(): ?int
    {
        try {
            $row = DB::selectOne('SHOW server_version_num');
            $version = (int) ($row->server_version_num ?? 0);

            return $version > 0 ? intdiv($version, 10000) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
