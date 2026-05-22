<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_deducts_stock_when_order_sent_to_kitchen(): void
    {
        $waiter = User::factory()->create(['role' => 'waiter', 'pin_code' => '9999']);
        $supply = Supply::create([
            'name' => 'Test Supply',
            'unit_type' => 'gram',
            'current_stock' => 1000,
            'min_stock' => 100,
            'cost_per_unit' => 0.10,
        ]);
        $product = Product::create([
            'name' => 'Test Dish',
            'price' => 50,
            'preparation_time' => 10,
            'active' => true,
        ]);
        Recipe::create([
            'product_id' => $product->id,
            'supply_id' => $supply->id,
            'quantity_required' => 100,
        ]);

        $order = Order::create([
            'table_number' => 1,
            'waiter_id' => $waiter->id,
            'status' => 'in_kitchen',
            'total' => 50,
        ]);
        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'subtotal' => 100,
        ]);

        $this->actingAs($waiter);
        app(InventoryEngine::class)->deductForOrder($order->fresh(['details.product.recipes.supply', 'details.modifiers.modifier.supply']));

        $supply->refresh();
        $this->assertEquals(800, (float) $supply->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'supply_id' => $supply->id,
            'type' => 'sale_consumption',
        ]);
    }
}
