<?php

namespace Tests\Feature;

use App\Jobs\PrintReceiptJob;
use App\Models\CashierCashCount;
use App\Models\CashierDailyClosure;
use App\Models\DailyReportZ;
use App\Models\InventoryLog;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Supply;
use App\Models\User;
use App\Services\CashierDailyClosureService;
use App\Services\InventoryEngine;
use App\Services\PrinterService;
use App\Services\ReportZService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    private User $waiter;

    private User $cashier;

    private RestaurantTable $table;

    private Product $product;

    private Supply $supply;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        config(['app.tax_rate' => 0]);
        Bus::fake();
        $this->waiter = User::factory()->create(['role' => 'waiter', 'pin_code' => '1001']);
        $this->cashier = User::factory()->create(['role' => 'cashier', 'pin_code' => '1002']);
        $this->table = RestaurantTable::create(['number' => 1, 'active' => true]);
        $this->product = Product::create(['name' => 'Almuerzo', 'price' => 1000, 'active' => true]);
        $this->supply = Supply::create([
            'name' => 'Arroz', 'unit_type' => 'gram', 'current_stock' => 1000,
            'min_stock' => 0, 'cost_per_unit' => 1,
        ]);
        Recipe::create(['product_id' => $this->product->id, 'supply_id' => $this->supply->id, 'quantity_required' => 100]);
    }

    private function createOrder(): Order
    {
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $this->orderInput())
            ->assertRedirect(route('waiter.orders'))->assertSessionHasNoErrors();

        return Order::sole();
    }

    private function orderInput(): array
    {
        return ['restaurant_table_id' => $this->table->id, 'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
        ]];
    }

    public function test_a_table_has_one_active_order_and_reserves_stock_once(): void
    {
        $this->createOrder();
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $this->orderInput())
            ->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', ['type' => 'sale_reserved', 'quantity' => -200]);
    }

    public function test_shortage_rolls_back_order_and_stock(): void
    {
        $this->supply->update(['current_stock' => 100]);
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $this->orderInput())->assertSessionHasErrors('inventory');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(100.0, (float) $this->supply->fresh()->current_stock);
    }

    public function test_print_failure_is_visible_without_reversing_the_sale(): void
    {
        $order = $this->createOrder();
        Bus::fake()->except(PrintReceiptJob::class);
        $this->mock(PrinterService::class)->shouldReceive('printReceipt')->once()->andReturnFalse();
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 2000])
            ->assertSessionHas('warning')->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->post(route('cashier.pay-order', $order), ['expected_total' => 2000])->assertSessionHas('error');
    }

    public function test_kitchen_can_mark_ready_but_cannot_collect_payment(): void
    {
        $order = $this->createOrder();
        $this->post(route('waiter.send-kitchen', $order));
        $this->actingAs(User::factory()->create(['role' => 'cook']))->get(route('cook.orders'))->assertOk()->assertSee('Almuerzo');
        $this->post(route('cook.mark-ready', $order))->assertSessionHasNoErrors();
        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->post(route('cashier.pay-order', $order), ['expected_total' => 2000])->assertForbidden();
    }

    public function test_product_tax_is_captured_before_catalog_or_general_rate_changes(): void
    {
        $this->product->update(['tax_type' => 'INC', 'tax_rate' => '0.0800']);
        $order = $this->createOrder();
        $this->assertSame(2160.0, (float) $order->total);
        $this->product->update(['tax_rate' => '0.1900']);
        config(['app.tax_rate' => 0.19]);
        $order->recalculateTotals();
        $this->assertSame(2160.0, (float) $order->fresh()->total);
        $this->assertSame('INC', $order->details()->sole()->tax_type);
    }

    public function test_comment_edit_preserves_sale_price_and_inventory_after_kitchen(): void
    {
        $order = $this->createOrder();
        $this->post(route('waiter.send-kitchen', $order))->assertSessionHasNoErrors();
        $this->product->update(['price' => 2000]);
        $this->actingAs($this->cashier)->put(route('cashier.order-details.update', $order->details()->sole()), [
            'product_id' => $this->product->id, 'quantity' => 2, 'comments' => 'Sin cebolla',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2000.0, (float) $order->fresh()->total);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_logs', 1);
    }

    public function test_kitchen_cancellation_records_waste_without_restoring_consumed_stock(): void
    {
        $order = $this->createOrder();
        $this->post(route('waiter.send-kitchen', $order))->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->post(route('cashier.cancel-order', $order), ['reason' => 'Cliente canceló comida preparada'])->assertSessionHasNoErrors();
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_logs', ['type' => 'manual_waste', 'quantity' => -200]);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_cancellation_distinguishes_prepared_details_from_unsent_additions(): void
    {
        $order = $this->createOrder();
        $this->post(route('waiter.send-kitchen', $order));
        $this->actingAs($this->cashier)->post(route('cashier.order-details.store', $order), [
            'product_id' => $this->product->id, 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(700.0, (float) $this->supply->fresh()->current_stock);
        $this->post(route('cashier.cancel-order', $order), ['reason' => 'Cancelar platos y reserva nueva'])->assertSessionHasNoErrors();
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertSame(-200.0, (float) InventoryLog::where('type', 'manual_waste')->sum('quantity'));
        $this->assertSame(100.0, (float) InventoryLog::where('type', 'sale_reversal')->sum('quantity'));
    }

    public function test_inactive_tables_and_products_cannot_be_ordered(): void
    {
        $this->table->update(['active' => false]);
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $this->orderInput())
            ->assertSessionHasErrors('restaurant_table_id');
        $this->table->update(['active' => true]);
        $this->product->update(['active' => false]);
        $this->post(route('waiter.store-order'), $this->orderInput())->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_negative_modifier_quantities_are_rejected_before_reservation(): void
    {
        $input = $this->orderInput();
        $input['items'][0]['modifiers'] = [['modifier_id' => Modifier::firstOrFail()->id, 'quantity' => -2]];
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $input)
            ->assertSessionHasErrors('items.0.modifiers.0.quantity');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1000.0, (float) $this->supply->fresh()->current_stock);
    }

    public function test_waiter_cannot_send_another_waiters_order_to_kitchen(): void
    {
        $order = $this->createOrder();
        $other = User::factory()->create(['role' => 'waiter', 'pin_code' => '1003']);
        $this->actingAs($other)->post(route('waiter.send-kitchen', $order))->assertForbidden();
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_repeated_edits_and_cancellation_restore_only_outstanding_reservations(): void
    {
        $order = $this->createOrder();
        $detail = $order->details()->sole();
        $this->actingAs($this->cashier);
        foreach ([3 => 700, 1 => 900] as $quantity => $remaining) {
            $this->put(route('cashier.order-details.update', $detail), [
                'product_id' => $this->product->id, 'quantity' => $quantity,
            ])->assertSessionHasNoErrors();
            $this->assertSame((float) $remaining, (float) $this->supply->fresh()->current_stock);
        }
        $this->post(route('cashier.cancel-order', $order), ['reason' => 'Cliente se retira'])->assertSessionHasNoErrors();
        $this->assertSame(1000.0, (float) $this->supply->fresh()->current_stock);
        $this->assertSame(0.0, (float) InventoryLog::where('supply_id', $this->supply->id)->sum('quantity'));
        $this->post(route('cashier.cancel-order', $order), ['reason' => 'Cliente se retira'])->assertSessionHas('error');
        $this->assertSame(1000.0, (float) $this->supply->fresh()->current_stock);
    }

    public function test_removed_details_require_a_reason_and_leave_an_audit_record(): void
    {
        $order = $this->createOrder();
        $detail = $order->details()->sole();
        $this->actingAs($this->cashier)->delete(route('cashier.order-details.destroy', $detail))
            ->assertSessionHasErrors('reason');
        $this->delete(route('cashier.order-details.destroy', $detail), ['reason' => 'Pedido equivocado'])
            ->assertRedirect(route('cashier.table-detail', 1));
        $this->assertTrue($order->fresh()->isCancelled());
        $this->assertSame(0.0, (float) $order->fresh()->total);
        $this->assertSame(1000.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'action' => 'detail_removed', 'user_id' => $this->cashier->id]);
    }

    public function test_transfer_preserves_order_and_rejects_occupied_destinations(): void
    {
        $order = $this->createOrder();
        $destination = RestaurantTable::create(['number' => 2, 'active' => true]);
        $this->actingAs($this->cashier)->post(route('cashier.transfer-order', $order), ['restaurant_table_id' => $this->table->id])
            ->assertSessionHasErrors('restaurant_table_id');
        $this->post(route('cashier.transfer-order', $order), ['restaurant_table_id' => $destination->id])
            ->assertRedirect(route('cashier.table-detail', 2));
        $this->assertNull($this->table->activeOrder());
        $this->assertSame($order->id, $destination->activeOrder()->id);
        $this->assertSame(2000.0, (float) $order->fresh()->total);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'action' => 'transferred']);
        $this->get(route('cashier.table-detail', 2))->assertOk()->assertSee('Trasladar a otra mesa');
    }

    public function test_payment_checks_the_displayed_total_and_cannot_be_repeated(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 1500])
            ->assertSessionHasErrors('expected_total');
        $this->assertTrue($order->fresh()->isPending());
        $this->post(route('cashier.pay-order', $order), ['expected_total' => 2000])->assertRedirect(route('cashier.pos'));
        $this->post(route('cashier.pay-order', $order), ['expected_total' => 2000])->assertSessionHas('error');
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::where('type', 'sale_confirmed')->count());
        Bus::assertDispatched(PrintReceiptJob::class, 1);
        $this->assertSame(1, DB::table('order_events')->where('action', 'paid')->count());
    }

    public function test_payment_failure_rolls_back_inventory_and_order_together(): void
    {
        $order = $this->createOrder();
        $this->mock(InventoryEngine::class, function ($mock) {
            $mock->shouldReceive('confirmForOrder')->once()->andReturnUsing(function (Order $order) {
                InventoryLog::where('order_id', $order->id)->update(['type' => 'sale_confirmed']);
                throw new \RuntimeException('Fallo simulado');
            });
        });
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 2000])
            ->assertServerError();
        $this->assertTrue($order->fresh()->isPending());
        $this->assertNull($order->fresh()->inventory_confirmed_at);
        $this->assertSame(0, InventoryLog::where('type', 'sale_confirmed')->count());
        Bus::assertNotDispatched(PrintReceiptJob::class);
    }

    public function test_locked_orders_cannot_be_paid_or_modified(): void
    {
        $order = $this->createOrder();
        $order->update(['locked_at' => now()]);
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 2000])
            ->assertSessionHasErrors('order');
        $this->post(route('cashier.order-details.store', $order), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('order_details', 1);
    }

    public function test_both_closures_reject_orders_from_previous_days(): void
    {
        $order = $this->createOrder();
        $order->forceFill(['created_at' => now()->subDay()])->save();
        foreach ([CashierDailyClosureService::class, ReportZService::class] as $service) {
            try {
                if ($service === CashierDailyClosureService::class) {
                    app($service)->closeDay($this->cashier, []);
                } else {
                    app($service)->generateReportZ($this->cashier);
                }
                $this->fail('The closure should reject an active order from yesterday.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('1', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('cashier_daily_closures', 0);
        $this->assertDatabaseCount('daily_reports_z', 0);
    }

    public function test_cash_closure_requires_a_cash_count(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Registra el arqueo');
        app(CashierDailyClosureService::class)->closeDay($this->cashier, []);
    }

    public function test_closed_cash_day_blocks_new_orders_payments_and_cash_count_changes(): void
    {
        $order = $this->createOrder();
        CashierDailyClosure::create(['fiscal_date' => today(), 'cashier_id' => $this->cashier->id, 'closed_at' => now()]);
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 2000])
            ->assertSessionHasErrors('order');
        $this->post(route('cashier.cash-count.store'), ['sales' => ['1000' => 2]])->assertSessionHasErrors('order');
        $other = RestaurantTable::create(['number' => 2, 'active' => true]);
        $input = $this->orderInput();
        $input['restaurant_table_id'] = $other->id;
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $input)->assertSessionHasErrors('order');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cashier_cash_counts', 0);
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_report_z_also_blocks_new_sales(): void
    {
        DailyReportZ::create(['fiscal_date' => today(), 'cashier_id' => $this->cashier->id]);
        $this->actingAs($this->waiter)->post(route('waiter.store-order'), $this->orderInput())->assertSessionHasErrors('order');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_count_uses_only_supported_denominations(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.cash-count.store'), ['sales' => ['1000' => 2, '12345' => 10]])
            ->assertSessionHasErrors('sales');
        $this->assertDatabaseCount('cashier_cash_counts', 0);
        session()->forget('errors');
        $this->post(route('cashier.cash-count.store'), ['sales' => ['1000' => 3]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cashier_cash_counts', 1);
        $this->assertSame(3000.0, (float) CashierCashCount::sole()->sales_total);
    }

    public function test_editing_then_paying_confirms_only_the_current_reservation(): void
    {
        $order = $this->createOrder();
        $detail = $order->details()->sole();
        $this->actingAs($this->cashier)->put(route('cashier.order-details.update', $detail), [
            'product_id' => $this->product->id, 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('cashier.pay-order', $order), ['expected_total' => 1000])->assertSessionHasNoErrors();
        $this->assertSame(-100.0, (float) InventoryLog::where('type', 'sale_confirmed')->sum('quantity'));
        $this->assertSame(900.0, (float) $this->supply->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::where('type', 'sale_reserved')->whereNotNull('reversed_at')->count());
    }

    public function test_cash_count_can_follow_report_z_and_cash_closure_preserves_its_totals(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $order), ['expected_total' => 2000])
            ->assertSessionHasNoErrors();
        DailyReportZ::create(['fiscal_date' => today(), 'cashier_id' => $this->cashier->id, 'total_sales' => 2000]);
        $this->post(route('cashier.cash-count.store'), [
            'base' => ['10000' => 1], 'sales' => ['1000' => 1, '500' => 1, '200' => 2],
        ])->assertRedirect()->assertSessionHasNoErrors();

        Pdf::shouldReceive('loadView')->once()->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('setOption')->once()->andReturnSelf();
        Pdf::shouldReceive('save')->once();

        $closure = app(CashierDailyClosureService::class)->closeDay($this->cashier, [['concept' => 'Compra hielo', 'amount' => 100]]);
        $this->assertSame(2000.0, (float) $closure->total_sales);
        $this->assertSame(1900.0, (float) $closure->expected_cash_total);
        $this->assertSame(11900.0, (float) $closure->declared_cash_total);
        $this->assertSame(0.0, (float) $closure->cash_difference);
        $this->assertSame(0.0, (float) $closure->difference_vs_report_z);
        $this->post(route('cashier.cash-count.store'), ['sales' => ['1000' => 9]])->assertSessionHasErrors('order');
        $this->assertSame(1900.0, (float) CashierCashCount::sole()->sales_total);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ya existe un cierre');
        app(CashierDailyClosureService::class)->closeDay($this->cashier, []);
    }

    public function test_operation_migration_can_be_reapplied_without_losing_orders_or_inventory_logs(): void
    {
        $order = $this->createOrder();
        $log = InventoryLog::sole();
        $path = 'database/migrations/2026_09_18_000001_add_pos_operation_controls.php';
        $this->artisan('migrate:rollback', ['--path' => [$path], '--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => 2000]);
        $this->assertDatabaseHas('inventory_logs', ['id' => $log->id, 'type' => 'sale_reserved', 'quantity' => -200]);
        $this->assertSame(800.0, (float) $this->supply->fresh()->current_stock);
        $this->assertDatabaseHas('pos_operation_locks', ['name' => 'operations']);
    }

    public function test_occupied_table_cannot_be_renumbered_or_disabled(): void
    {
        $this->createOrder();
        $admin = User::factory()->create(['role' => 'administrator', 'pin_code' => '1004']);
        foreach ([['number' => 2, 'active' => true], ['number' => 1, 'active' => false]] as $changes) {
            $this->actingAs($admin)->put(route('admin.tables.update', $this->table), $changes + ['zone' => 'Salón', 'capacity' => 4])
                ->assertSessionHasErrors('number');
        }
        $this->assertSame(1, $this->table->fresh()->number);
        $this->assertTrue($this->table->fresh()->active);
    }
}
