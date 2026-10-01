<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Isolated PostgreSQL regression runner. Never migrates the configured application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$configuration = config('database.connections.pgsql');
$name = 'erp_fiscal_qa_'.bin2hex(random_bytes(8));
$connection = DB::connection('pgsql');
if (config('database.default') !== 'pgsql') {
    throw new RuntimeException('Run this smoke test only from a PostgreSQL-configured application.');
}
$connection->statement('CREATE DATABASE "'.$name.'"');
try {
    $process = new Process([
        PHP_BINARY, base_path('vendor/phpunit/phpunit/phpunit'), '--filter',
        'FiscalDocumentWorkflowTest|PosWorkflowTest|ModifierMigrationsTest',
    ], base_path(), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $name,
        'DB_HOST' => $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
        'DB_USERNAME' => $configuration['username'], 'DB_PASSWORD' => $configuration['password'],
        'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync',
    ]);
    $process->setTimeout(120);
    $process->run(fn ($type, $buffer) => print ($buffer));
    $result = $process->getExitCode();
} finally {
    // Only the exact random database created by this process is removed.
    $connection->statement('DROP DATABASE "'.$name.'"');
}
exit($result ?? 1);
