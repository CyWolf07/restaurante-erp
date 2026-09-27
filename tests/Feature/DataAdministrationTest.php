<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\InventoryPurchase;
use App\Models\Order;
use App\Models\PhysicalInventory;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Supply;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CashierDailyClosureService;
use App\Services\CommerceInventoryImportService;
use App\Services\CommerceInventoryMetricsService;
use App\Services\InventoryEngine;
use App\Services\MonthlyReportService;
use App\Services\ProgrammerPanelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DataAdministrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_setup_preserves_key_and_pin_login(): void
    {
        $user = User::factory()->create(['pin_code' => '0932']);
        $key = config('app.key');
        $this->artisan('app:ensure-key')->assertExitCode(0);
        $this->assertSame($key, config('app.key'));
        $this->assertSame($user->id, User::findByPin('0932')->id);
    }

    public function test_quick_purchase_is_documented_exact_and_not_duplicated_in_metrics(): void
    {
        $user = User::factory()->create();
        $supply = Supply::create(['name' => 'Harina', 'unit_type' => 'gram', 'current_stock' => 0]);
        $key = (string) Str::uuid();
        foreach ([1, 2] as $attempt) {
            app(InventoryEngine::class)->registerPurchase($supply, 1.005, $user->id, 'Compra', $key, '1.00');
        }
        $this->assertSame('1.01', InventoryPurchase::sole()->total_value);
        $this->assertSame('1.0050', $supply->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::count());
        $supplies = collect([$supply->fresh()]);
        app(CommerceInventoryMetricsService::class)->attachToSupplies($supplies);
        $this->assertEquals(1.005, $supplies[0]->commerce_metrics['entrada']);
        $this->assertEquals(1.01, $supplies[0]->commerce_metrics['v_entrada']);
    }

    public function test_consumption_comparison_uses_two_queries_and_excludes_reversals(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 6) as $index) {
            $supply = Supply::create(['name' => 'Insumo '.$index, 'unit_type' => 'unit']);
            foreach (['sale_confirmed' => -2, 'manual_waste' => -1] as $type => $quantity) {
                InventoryLog::create(['supply_id' => $supply->id, 'user_id' => $user->id,
                    'type' => $type, 'quantity' => $quantity, 'stock_after' => 0, 'created_at' => now()]);
            }
        }
        InventoryLog::create(['supply_id' => $supply->id, 'user_id' => $user->id,
            'type' => 'sale_confirmed', 'quantity' => -100, 'stock_after' => 0,
            'created_at' => now(), 'reversed_at' => now()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(AnalyticsService::class)->getConsumptionComparison();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
        $this->assertSame(array_fill(0, 6, 2.0), $result['theoretical']);
        $this->assertSame(array_fill(0, 6, 3.0), $result['real']);
    }

    public function test_unknown_count_is_rejected_atomically(): void
    {
        $user = User::factory()->create();
        try {
            app(AnalyticsService::class)->analyzePhysicalInventory($user, [(string) Str::uuid() => 12]);
            $this->fail('Unknown supplies must not be silently ignored.');
        } catch (ValidationException) {
            $this->assertSame(0, PhysicalInventory::count());
        }
    }

    public function test_restart_never_clears_pending_or_failed_jobs(): void
    {
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);
        $result = app(ProgrammerPanelService::class)->killStalledProcesses();
        $this->assertStringContainsString('conservados', implode(' ', $result));
    }

    public function test_order_totals_round_tax_once_using_decimal_arithmetic(): void
    {
        config(['app.tax_rate' => '0.19']);
        $user = User::factory()->create();
        $table = RestaurantTable::create(['number' => 1]);
        $product = Product::create(['name' => 'Prueba', 'price' => '0.10']);
        $order = Order::create(['table_number' => 1, 'restaurant_table_id' => $table->id, 'waiter_id' => $user->id, 'status' => 'pending']);
        foreach (['0.10', '0.20', '0.30'] as $amount) {
            $order->details()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $amount, 'subtotal' => $amount]);
        }
        $order->recalculateTotals();
        $this->assertSame('0.60', $order->subtotal);
        $this->assertSame('0.11', $order->tax);
        $this->assertSame('0.71', $order->total);
    }

    public function test_cash_count_rejects_overflow_and_audits_valid_changes(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->post(route('cashier.cash-count.store'), [
            'base' => ['100000' => 99999], 'sales' => ['100000' => 99999],
        ])->assertSessionHasErrors('sales');
        $this->assertDatabaseCount('cashier_cash_counts', 0);
        session()->forget('errors');
        $this->post(route('cashier.cash-count.store'), ['sales' => ['1000' => 3]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['action' => 'cash_count_saved', 'user_id' => $cashier->id]);
    }

    public function test_blind_count_does_not_show_or_prefill_stock(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Supply::create(['name' => 'Harina', 'unit_type' => 'gram', 'current_stock' => '87654.3210']);
        $this->actingAs($admin)->get(route('admin.physical-inventory'))->assertOk()
            ->assertDontSee('87654')->assertSee('Sin contar');
    }

    public function test_cash_summary_counts_a_previous_day_order_on_its_payment_day(): void
    {
        $user = User::factory()->create();
        $order = Order::create(['table_number' => 1, 'waiter_id' => $user->id, 'status' => 'paid',
            'subtotal' => '100.00', 'tax' => '19.00', 'total' => '119.00',
            'created_at' => now()->subDay(), 'inventory_confirmed_at' => now()]);
        $order->forceFill(['created_at' => now()->subDay()])->save();
        $service = app(CashierDailyClosureService::class);
        $this->assertEquals(119, $service->dailySummary()['total_sales']);
        $this->assertEquals(0, $service->dailySummary(today()->subDay())['total_sales']);
    }

    public function test_legacy_purchases_show_missing_cost_without_inventing_documents(): void
    {
        $user = User::factory()->create();
        $supply = Supply::create(['name' => 'Histórico', 'unit_type' => 'unit']);
        InventoryLog::create(['supply_id' => $supply->id, 'user_id' => $user->id,
            'type' => 'supplier_purchase', 'quantity' => 4, 'stock_after' => 4,
            'unit_cost' => null, 'created_at' => now()]);
        $supplies = collect([$supply]);
        app(CommerceInventoryMetricsService::class)->attachToSupplies($supplies);
        $this->assertEquals(4, $supply->commerce_metrics['entrada']);
        $this->assertTrue($supply->commerce_metrics['purchase_value_incomplete']);
        $this->assertDatabaseCount('inventory_purchases', 0);
    }

    public function test_csv_preview_does_not_write_the_operation_lock(): void
    {
        DB::table('pos_operation_locks')->update(['used_at' => '2020-01-01 00:00:00']);
        $file = UploadedFile::fake()->createWithContent('supplies.csv', "CODIGO,ARTICULO,FAMILIA,P.V.P,STOCK,UNIDAD,COSTO\nA1,Arroz,granos,4,30,gram,2\n");
        $result = app(CommerceInventoryImportService::class)->import($file, true, true);
        $this->assertTrue($result['preview']);
        $this->assertSame('2020-01-01 00:00:00', DB::table('pos_operation_locks')->value('used_at'));
        $this->assertDatabaseCount('supplies', 0);
    }

    public function test_monthly_pdf_renders_after_the_snapshot_transaction(): void
    {
        $user = User::factory()->create();
        $renderer = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $renderer->shouldReceive('setPaper')->once()->andReturnSelf();
        $renderer->shouldReceive('setOption')->once()->andReturnSelf();
        $renderer->shouldReceive('save')->once()->andReturnSelf();
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function () use ($renderer) {
            $this->assertSame(0, DB::transactionLevel());

            return $renderer;
        });
        $period = now()->subMonthNoOverflow();
        $report = app(MonthlyReportService::class)->closeMonth($user, $period->year, $period->month);
        $this->assertNotEmpty($report->snapshot_data);
        $this->assertNotNull($report->pdf_local_path);
    }

    public function test_product_catalog_search_is_paginated(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        foreach (range(1, 26) as $index) {
            Product::create(['name' => sprintf('Plato %02d', $index), 'price' => 1]);
        }
        $this->actingAs($user)->get(route('admin.products'))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->count() === 25 && $products->total() === 26);
        $this->get(route('admin.products', ['q' => 'Plato 26']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1);
    }

    public function test_price_only_update_preserves_recipe_and_its_version(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $supply = Supply::create(['name' => 'Arroz', 'unit_type' => 'gram']);
        $product = Product::create(['name' => 'Plato', 'price' => 100]);
        $recipe = $product->recipes()->create(['supply_id' => $supply->id, 'quantity_required' => 12]);
        $version = $product->fresh()->recipe_version;
        $this->actingAs($user)->put(route('admin.products.update', $product), [
            'name' => 'Plato', 'price' => 200, 'preparation_time' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(200, $product->fresh()->price);
        $this->assertSame($recipe->id, $product->recipes()->sole()->id);
        $this->assertSame($version, $product->fresh()->recipe_version);
    }

    public function test_discount_larger_than_the_line_is_rejected(): void
    {
        $this->assertSame('0.29', \App\Support\Money::lineTotal('0.10', 3, '0.01'));
        $this->expectException(ValidationException::class);
        \App\Support\Money::lineTotal('100.00', 1, '100.01');
    }

    public function test_line_total_cannot_overflow_postgresql_money_columns(): void
    {
        $this->expectException(ValidationException::class);
        \App\Support\Money::lineTotal('9999999999.99', 2);
    }
}
