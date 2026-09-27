<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\User;
use App\Services\ProductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionConcurrencyTest extends TestCase
{
    public function test_two_simultaneous_batches_cannot_consume_the_same_availability(): void
    {
        $this->race(false);
    }

    public function test_two_simultaneous_confirmations_produce_one_movement_set(): void
    {
        $this->race(true);
    }

    private function race(bool $sameOrder): void
    {
        $driver = config('database.default');
        $sqlite = $driver === 'sqlite' ? tempnam(sys_get_temp_dir(), 'erp-race-db-') : null;
        if ($sqlite) {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $sqlite]);
        }
        $base = sys_get_temp_dir().'/erp-race-'.Str::uuid();
        $files = [$base.'-a', $base.'-b', $base.'-release'];
        $processes = [];
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            $user = User::factory()->create(['role' => 'cook']);
            $input = Supply::create(['name' => 'Input', 'unit_type' => 'gram', 'current_stock' => 100, 'cost_per_unit' => 1]);
            $output = Supply::create(['name' => 'Output', 'unit_type' => 'unit', 'current_stock' => 0]);
            $product = Product::create(['name' => 'Recipe', 'price' => 0, 'active' => true]);
            Recipe::create(['product_id' => $product->id, 'supply_id' => $input->id, 'quantity_required' => 10]);
            $data = ['product_id' => $product->id, 'output_supply_id' => $output->id, 'planned_quantity' => 6];
            $first = app(ProductionService::class)->create($data + ['request_key' => (string) Str::uuid()], $user);
            $second = $sameOrder ? $first : app(ProductionService::class)->create($data + ['request_key' => (string) Str::uuid()], $user);
            $connection = DB::connection()->getConfig();
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => $driver,
                'DB_DATABASE' => $connection['database'], 'DB_HOST' => $connection['host'] ?? '127.0.0.1',
                'DB_PORT' => (string) ($connection['port'] ?? 5432), 'DB_USERNAME' => $connection['username'] ?? '',
                'DB_PASSWORD' => $connection['password'] ?? '', 'DB_URL' => '', 'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync'];
            foreach ([$first, $second] as $i => $order) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/complete_production.php'), $order->id, $user->id, $files[$i], $files[2]], base_path(), $env);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (! is_file($files[0]) || ! is_file($files[1])) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers failed to reach the synchronization barrier.');
                }
                usleep(10000);
            }
            file_put_contents($files[2], 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = $process->getOutput();
            }
            sort($results);
            $this->assertSame($sameOrder ? ['completed', 'completed'] : ['completed', 'unavailable'], $results);
            $this->assertEquals(40, $input->fresh()->current_stock);
            $this->assertEquals(6, $output->fresh()->current_stock);
            $this->assertSame(2, InventoryLog::count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if ($sqlite) {
                DB::purge('sqlite');
                $files = array_merge($files, [$sqlite, $sqlite.'-wal', $sqlite.'-shm']);
            }
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
