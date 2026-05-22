<?php

namespace App\Http\Controllers;

use App\Jobs\PrintKitchenTicketJob;
use App\Jobs\PrintPreticketJob;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailModifier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantTable;
use App\Services\InventoryEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WaiterController extends Controller
{
    public function orders()
    {
        $orders = Order::where('waiter_id', Auth::id())
            ->whereIn('status', ['pending', 'in_kitchen', 'ready'])
            ->with(['details.product', 'kitchenSentBy', 'waiter'])
            ->latest()
            ->get();

        return view('waiter.orders', compact('orders'));
    }

    public function createOrder()
    {
        $categories = ProductCategory::orderBy('sort_order')
            ->with(['products' => fn($q) => $q->active()->orderBy('sort_order')])
            ->get();
        $modifiers = Modifier::active()->get();
        $tables = RestaurantTable::active()->ordered()->get();

        $storeRoute = request()->routeIs('cashier.delivery.*')
            ? route('cashier.delivery.store')
            : route('waiter.store-order');
        $screenTitle = request()->routeIs('cashier.delivery.*') ? 'Domicilios' : 'Nueva Orden';
        $screenSubtitle = request()->routeIs('cashier.delivery.*')
            ? 'Crea ordenes para mesas de domicilio y envialas a cocina'
            : 'Selecciona los platos y envia a cocina';

        return view('waiter.create-order', compact('categories', 'modifiers', 'tables', 'storeRoute', 'screenTitle', 'screenSubtitle'));
    }

    public function storeOrder(Request $request, InventoryEngine $engine)
    {
        $request->validate([
            'restaurant_table_id' => 'required|exists:restaurant_tables,id',
            'items'        => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $restaurantTable = RestaurantTable::findOrFail($request->restaurant_table_id);

        if (Order::forTable($restaurantTable->number)->exists()) {
            return back()->with('error', "La {$restaurantTable->display_name} ya tiene una orden activa.");
        }

        $order = DB::transaction(function () use ($request, $engine, $restaurantTable) {
            $order = Order::create([
                'table_number'        => $restaurantTable->number,
                'restaurant_table_id' => $restaurantTable->id,
                'waiter_id'           => Auth::id(),
                'status'              => 'pending',
            ]);

            // Crear detalles de la orden
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $detail = OrderDetail::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal'   => $product->price * $item['quantity'],
                    'comments'   => $item['comments'] ?? null,
                ]);

                // Agregar modificadores si existen
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $modifier = Modifier::findOrFail($mod['modifier_id']);
                        $qty = $mod['quantity'] ?? 1;
                        OrderDetailModifier::create([
                            'order_detail_id' => $detail->id,
                            'modifier_id'     => $modifier->id,
                            'quantity'         => $qty,
                            'unit_price'       => $modifier->price,
                            'subtotal'         => $modifier->price * $qty,
                        ]);
                    }
                }
            }

            // Recalcular totales
            $order->load('details.modifiers');
            $subtotal = $order->details->sum('subtotal') + $order->details->flatMap->modifiers->sum('subtotal');
            $taxRate = (float) config('app.tax_rate', 0.16);
            $order->update([
                'subtotal' => $subtotal,
                'tax'      => round($subtotal * $taxRate, 2),
                'total'    => round($subtotal * (1 + $taxRate), 2),
            ]);

            $order->load('details.product', 'details.modifiers');
            $engine->reserveForOrder($order);

            return $order->fresh();
        });

        PrintPreticketJob::dispatchSync($order);

        $redirectRoute = $request->routeIs('cashier.delivery.store') ? 'cashier.pos' : 'waiter.orders';

        return redirect()->route($redirectRoute)
            ->with('success', "Orden Mesa #{$order->table_number} creada. Inventario reservado y pre-ticket impreso.");
    }

    public function sendToKitchen(Order $order, InventoryEngine $engine)
    {
        if (!$order->isPending()) {
            return back()->with('error', 'Esta orden ya no está pendiente.');
        }

        $order->update([
            'status'          => 'in_kitchen',
            'kitchen_sent_by' => Auth::id(),
            'kitchen_sent_at' => now(),
        ]);
        PrintKitchenTicketJob::dispatchSync($order->fresh(['waiter', 'kitchenSentBy']));

        return back()->with('success', "Orden Mesa #{$order->table_number} enviada a cocina e impresa.");
    }

    public function printPreticket(Order $order)
    {
        if ($order->waiter_id !== Auth::id() && !Auth::user()->isProgrammer() && !Auth::user()->isAdministrator()) {
            abort(403);
        }

        if (!in_array($order->status, ['pending', 'in_kitchen', 'ready'], true)) {
            return back()->with('error', 'No se puede imprimir pre-ticket de una orden cerrada.');
        }

        PrintPreticketJob::dispatchSync($order->fresh(['details.product', 'details.modifiers.modifier', 'waiter', 'restaurantTable']));

        return back()->with('success', "Pre-ticket Mesa #{$order->table_number} enviado a impresora.");
    }
}
