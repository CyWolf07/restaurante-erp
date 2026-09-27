<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\User;
use App\Services\ProductionService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    private User $cook;

    private Supply $input;

    private Supply $output;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->cook = User::factory()->create(['role' => 'cook']);
        $this->input = Supply::create(['name' => 'Harina', 'unit_type' => 'gram', 'current_stock' => 100, 'cost_per_unit' => 2]);
        $this->output = Supply::create(['name' => 'Masa', 'unit_type' => 'unit', 'current_stock' => 0, 'cost_per_unit' => 0]);
        $this->product = Product::create(['name' => 'Ficha de masa', 'price' => 0, 'active' => true]);
        Recipe::create(['product_id' => $this->product->id, 'supply_id' => $this->input->id, 'quantity_required' => 10]);
    }

    private function data(): array
    {
        return ['request_key' => (string) Str::uuid(), 'product_id' => $this->product->id,
            'output_supply_id' => $this->output->id, 'planned_quantity' => 3];
    }

    public function test_snapshot_survives_recipe_changes_and_double_completion_has_no_extra_movements(): void
    {
        $service = app(ProductionService::class);
        $data = $this->data();
        $order = $service->create($data, $this->cook);
        $this->assertSame($order->id, $service->create($data, $this->cook)->id);
        $this->product->recipes()->update(['quantity_required' => 99]);
        $service->complete($order, 2, $this->cook);
        $service->complete($order, 2, $this->cook);
        $this->assertEquals(70, $this->input->fresh()->current_stock);
        $this->assertEquals(2, $this->output->fresh()->current_stock);
        $this->assertEquals(30, $this->output->fresh()->cost_per_unit);
        $this->assertEquals(60, $order->fresh()->total_cost);
        $this->assertSame(2, InventoryLog::where('production_order_id', $order->id)->count());
        $this->assertSame(1, ProductionOrder::count());
        $this->actingAs($this->cook)->get(route('production.index'))->assertOk()->assertSee('Terminado');
    }

    public function test_unavailable_output_rolls_back_all_ingredient_movements(): void
    {
        $service = app(ProductionService::class);
        $order = $service->create($this->data(), $this->cook);
        $this->output->update(['active' => false]);
        try {
            $service->complete($order, 3, $this->cook);
            $this->fail('Invalid output must fail.');
        } catch (ValidationException) {
            $this->assertEquals(100, $this->input->fresh()->current_stock);
            $this->assertSame(0, InventoryLog::count());
            $this->assertSame('draft', $order->fresh()->status);
        }
    }

    public function test_insufficient_stock_and_cancelled_orders_cannot_be_completed(): void
    {
        $service = app(ProductionService::class);
        $order = $service->create($this->data(), $this->cook);
        $this->input->update(['current_stock' => 5]);
        try {
            $service->complete($order, 3, $this->cook);
            $this->fail('Shortage must fail.');
        } catch (ValidationException) {
            $this->assertSame(0, InventoryLog::count());
        }
        $service->cancel($order, 'Cambio de planificación', $this->cook);
        $this->expectException(ValidationException::class);
        $service->complete($order, 3, $this->cook);
    }

    public function test_cashier_cannot_start_production_and_recipe_cannot_consume_its_own_output(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->post(route('production.store'), $this->data())->assertForbidden();
        $this->expectException(ValidationException::class);
        app(ProductionService::class)->create(array_replace($this->data(), ['output_supply_id' => $this->input->id]), $this->cook);
    }

    public function test_production_migration_can_be_reapplied_with_existing_inventory(): void
    {
        $migration = require database_path('migrations/2026_09_21_000002_create_production_orders.php');
        $migration->down();
        $migration->up();
        $order = app(ProductionService::class)->create($this->data(), $this->cook);
        app(ProductionService::class)->complete($order, 3, $this->cook);
        $this->assertSame(2, InventoryLog::count());
    }

    public function test_reapplying_pos_controls_does_not_narrow_production_movement_types(): void
    {
        $service = app(ProductionService::class);
        $order = $service->create($this->data(), $this->cook);
        $service->complete($order, 3, $this->cook);
        $migration = require database_path('migrations/2026_09_18_000001_add_pos_operation_controls.php');
        $migration->down();
        $migration->up();
        $second = $service->create($this->data(), $this->cook);
        $service->complete($second, 3, $this->cook);
        $this->assertSame(4, InventoryLog::count());
    }
}
