<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (DB::connection()->getDriverName() !== 'pgsql') {
    throw new RuntimeException('La inicialización Render requiere PostgreSQL.');
}

// One transaction: migrations and API restrictions become visible together.
DB::transaction(function () {
    DB::statement('SELECT pg_advisory_xact_lock(714208305)');
    $status = Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();
    if ($status !== 0) {
        throw new RuntimeException('No se completaron las migraciones del ERP.');
    }
    $tables = ['migrations'];
    foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
        preg_match_all("/Schema::create\(['\"]([a-z_]+)['\"]/", file_get_contents($file), $matches);
        $tables = array_merge($tables, $matches[1]);
    }
    foreach (array_unique($tables) as $table) {
        if (! Schema::hasTable($table)) {
            continue;
        }
        DB::statement('ALTER TABLE public."'.$table.'" ENABLE ROW LEVEL SECURITY');
        foreach (['anon', 'authenticated'] as $role) {
            if (DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role])) {
                DB::statement('REVOKE ALL ON TABLE public."'.$table.'" FROM "'.$role.'"');
            }
        }
    }
});
echo "Base del ERP inicializada. No se sembraron usuarios ni ventas.\n";
