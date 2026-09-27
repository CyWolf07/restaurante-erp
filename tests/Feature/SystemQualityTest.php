<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\User;
use App\Services\CommerceInventoryImportService;
use App\Services\CommerceInventoryMetricsService;
use App\Services\InventoryCatalogService;
use App\Services\InventoryEngine;
use App\Services\MonthlyReportService;
use App\Services\ProgrammerPanelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemQualityTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($this->admin);
    }

    private function supply(): Supply
    {
        return app(InventoryCatalogService::class)->create([
            'code' => 'A1', 'name' => 'Arroz', 'unit_type' => 'gram',
            'current_stock' => 100, 'min_stock' => 5, 'cost_per_unit' => 2,
            'point' => 'bodega', 'family' => 'granos_abarrotes', 'pvp' => 3,
        ], $this->admin->id);
    }

    public function test_opening_balance_adjustment_and_stale_submission_are_traced(): void
    {
        $supply = $this->supply();
        $this->assertEquals(100, $supply->calculateTheoreticalStock());
        $service = app(InventoryCatalogService::class);
        $service->adjust($supply, -5, 100, 'Conteo autorizado', $this->admin->id);
        $this->assertEquals(95, $supply->fresh()->current_stock);
        $this->assertEquals(95, $supply->calculateTheoreticalStock());
        try {
            $service->adjust($supply, -5, 100, 'Solicitud repetida', $this->admin->id);
            $this->fail('A stale adjustment must be rejected.');
        } catch (ValidationException) {
            $this->assertEquals(95, $supply->fresh()->current_stock);
            $this->assertSame(2, InventoryLog::count());
        }
    }

    public function test_catalog_cannot_overwrite_stock_or_change_historical_units(): void
    {
        $supply = $this->supply();
        foreach ([['current_stock' => 500], ['unit_type' => 'unit']] as $data) {
            try {
                app(InventoryCatalogService::class)->update($supply, $data);
                $this->fail('Expected validation error.');
            } catch (ValidationException) {
                $this->assertEquals(100, $supply->fresh()->current_stock);
                $this->assertSame('gram', $supply->fresh()->unit_type);
            }
        }
    }

    public function test_import_preview_and_reimport_preserve_existing_stock(): void
    {
        $supply = $this->supply();
        $file = UploadedFile::fake()->createWithContent('catalog.csv', "CODIGO,ARTICULO,FAMILIA,P.V.P,STOCK,UNIDAD,COSTO\nA1,Arroz nuevo,granos,3,999,unit,2\nB1,Frijol,granos,4,30,gram,1\n");
        $service = app(CommerceInventoryImportService::class);
        $preview = $service->import($file, true, true);
        $this->assertSame(1, $preview['created']);
        $this->assertSame(1, $preview['updated']);
        $this->assertSame(1, Supply::count());
        $service->import($file);
        $service->import($file);
        $this->assertEquals(100, $supply->fresh()->current_stock);
        $this->assertSame('gram', $supply->fresh()->unit_type);
        $this->assertEquals(5, $supply->fresh()->min_stock);
        $this->assertSame(2, Supply::count());
        $this->assertSame(2, InventoryLog::count());
    }

    public function test_invalid_csv_is_atomic_and_does_not_invent_unit_conversions(): void
    {
        $file = UploadedFile::fake()->createWithContent('invalid.csv', "CODIGO,FAMILIA,P.V.P,STOCK,UNIDAD\nA1,granos,10,3,gram\nB1,granos,10,2,kg\n");
        $result = app(CommerceInventoryImportService::class)->import($file);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(0, Supply::count());
        $this->assertSame(0, InventoryLog::count());
    }

    public function test_physical_and_available_balances_include_waste_and_reservations(): void
    {
        $supply = $this->supply();
        app(InventoryEngine::class)->registerWaste($supply, 5, $this->admin->id, 'Merma');
        $supply->refresh()->update(['current_stock' => 85]);
        InventoryLog::create([
            'supply_id' => $supply->id, 'type' => 'sale_reserved', 'quantity' => -10,
            'stock_after' => 85, 'user_id' => $this->admin->id, 'created_at' => now(),
        ]);
        $supplies = collect([$supply->fresh()]);
        app(CommerceInventoryMetricsService::class)->attachToSupplies($supplies);
        $metrics = $supplies->first()->commerce_metrics;
        $this->assertEquals(85, $metrics['stock']);
        $this->assertEquals(95, $metrics['physical']);
        $this->assertEquals(10, $metrics['reserved']);
    }

    public function test_integrity_review_never_replaces_legacy_opening_balance(): void
    {
        $supply = Supply::create(['name' => 'Legacy', 'unit_type' => 'unit', 'current_stock' => 70]);
        $results = app(ProgrammerPanelService::class)->repairInventoryIntegrity($this->admin->id);
        $this->assertTrue($results[0]['review_required']);
        $this->assertFalse($results[0]['repaired']);
        $this->assertEquals(70, $supply->fresh()->current_stock);
        $this->assertSame(0, InventoryLog::count());
    }

    public function test_invalid_recipe_does_not_partially_update_product(): void
    {
        $supply = $this->supply();
        $product = Product::create(['name' => 'Original', 'price' => 20]);
        Recipe::create(['product_id' => $product->id, 'supply_id' => $supply->id, 'quantity_required' => 2]);
        $this->put(route('admin.products.update', $product), [
            'name' => 'Changed', 'price' => 30, 'preparation_time' => 10,
            'recipes' => [['supply_id' => $supply->id, 'quantity_required' => -1]],
        ])->assertSessionHasErrors('recipes.0.quantity_required');
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertEquals(2, $product->recipes()->sole()->quantity_required);
    }

    public function test_historical_costs_and_payment_period_survive_catalog_changes(): void
    {
        $supply = $this->supply();
        $product = Product::create(['name' => 'Plato', 'price' => 20]);
        Recipe::create(['product_id' => $product->id, 'supply_id' => $supply->id, 'quantity_required' => 2]);
        $order = Order::create(['table_number' => 1, 'waiter_id' => $this->admin->id, 'status' => 'pending', 'subtotal' => 20, 'total' => 20]);
        $order->update(['created_at' => now()->subMonths(2)]);
        OrderDetail::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 20, 'subtotal' => 20]);
        app(InventoryEngine::class)->confirmForOrder($order);
        $order->update(['status' => 'paid']);
        $service = app(MonthlyReportService::class);
        $before = $service->buildSnapshot(now()->startOfMonth(), now()->endOfMonth());
        $supply->update(['cost_per_unit' => 99]);
        $product->recipes()->update(['quantity_required' => 50]);
        $after = $service->buildSnapshot(now()->startOfMonth(), now()->endOfMonth());
        $this->assertEquals(4, $after['totals']['ingredient_cost']);
        $this->assertSame($before['totals'], $after['totals']);
        $this->assertEquals(1, $after['totals']['orders_count']);
        $this->assertFalse($after['totals']['cost_incomplete']);
        InventoryLog::where('type', 'sale_confirmed')->update(['unit_cost' => null]);
        $this->assertTrue($service->buildSnapshot(now()->startOfMonth(), now()->endOfMonth())['totals']['cost_incomplete']);
    }

    public function test_current_and_future_months_cannot_be_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        app(MonthlyReportService::class)->closeMonth($this->admin, (int) now()->year, (int) now()->month);
    }

    public function test_catalog_search_works_in_both_engines(): void
    {
        $this->supply();
        $this->get(route('admin.inventory.index', ['q' => 'ARROZ']))->assertOk()->assertSee('Arroz');
        $this->get(route('admin.staff.index', ['q' => 'TEST']))->assertOk();
    }

    public function test_cost_migration_preserves_rows_and_can_be_reapplied(): void
    {
        $supply = $this->supply();
        $migration = require database_path('migrations/2026_09_21_000001_add_inventory_cost_snapshots.php');
        $migration->down();
        $this->assertEquals(100, Supply::findOrFail($supply->id)->current_stock);
        $migration->up();
        $this->assertNull(InventoryLog::sole()->unit_cost);
    }
}
