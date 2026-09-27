<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_sqlite_backup_includes_committed_wal_data_and_can_be_restored(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'erp-backup-test-');
        $db = new \SQLite3($source);
        $copies = [];
        try {
            $db->exec('PRAGMA journal_mode=WAL');
            $db->exec('CREATE TABLE example (id INTEGER PRIMARY KEY, value TEXT)');
            $db->exec("INSERT INTO example VALUES (1, 'committed')");
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);
            $copies[] = app(DatabaseBackupService::class)->backup();
            $copies[] = app(DatabaseBackupService::class)->backup();
            $this->assertNotSame($copies[0], $copies[1]);
            foreach ($copies as $copy) {
                $restored = new \SQLite3($copy, SQLITE3_OPEN_READONLY);
                $this->assertSame('committed', $restored->querySingle('SELECT value FROM example'));
                $this->assertSame('ok', $restored->querySingle('PRAGMA integrity_check'));
                $restored->close();
            }
        } finally {
            $db->close();
            foreach (array_merge($copies, [$source, $source.'-wal', $source.'-shm']) as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
