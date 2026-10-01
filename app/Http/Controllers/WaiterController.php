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
use App\Services\PosOperationService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaiterController extends Controller
{
    public function orders()
    {
        $orders = Order::where('waiter_id', Auth::id())
            ->whereIn('status', ['pending', 'in_kitchen', 'ready'])
            ->with(['details.product', 'kitchenSentBy', 'waiter', 'restaurantTable'])
            ->latest()
            ->get();

        return view('waiter.orders', compact('orders'));
    }

    public function createOrder()
    {
        $categories = ProductCategory::orderBy('sort_order')
            ->with([
                'products' => fn ($q) => $q->active()->orderBy('sort_order'),
                'modifiers' => fn ($q) => $q->where('modifiers.active', true)->where('modifiers.type', 'option')->wherePivot('enabled', true),
            ])
            ->get();
        $modifiers = Modifier::active()->get();
        $isDeliveryScreen = request()->routeIs('cashier.delivery.*');
        if ($isDeliveryScreen) {
            $this->ensureDeliveryTables();
        }
        $tables = $isDeliveryScreen
            ? RestaurantTable::active()->where('zone', 'Domicilios')->ordered()->get()
            : RestaurantTable::active()->ordered()->get();

        $storeRoute = $isDeliveryScreen
            ? route('cashier.delivery.store')
            : route('waiter.store-order');
        $screenTitle = $isDeliveryScreen ? 'Domicilios' : 'Nueva Orden';
        $screenSubtitle = $isDeliveryScreen
            ? 'Crea ordenes para mesas de domicilio y envialas a cocina'
            : 'Selecciona los platos y envia a cocina';

        // Construir mapa de opciones por producto para el JS
        $productOptions = [];
        foreach ($categories as $cat) {
            $catOptions = $cat->modifiers->groupBy('group');
            foreach ($cat->products as $product) {
                $options = $product->uses_product_modifiers
                    ? $product->getEffectiveOptions()
                    : $catOptions;
                if ($options->isNotEmpty()) {
                    $productOptions[$product->id] = $options->map(fn ($group) => $group->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                    ])->values());
                }
            }
        }

        return view('waiter.create-order', compact('categories', 'modifiers', 'tables', 'storeRoute', 'screenTitle', 'screenSubtitle', 'productOptions'));
    }

    public function storeOrder(Request $request, InventoryEngine $engine)
    {
        $request->validate([
            'restaurant_table_id' => ['required', Rule::exists('restaurant_tables', 'id')->where('active', true)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('active', true)],
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'items.*.comments' => 'nullable|string|max:1000',
            'items.*.modifiers' => 'nullable|array|max:100',
            'items.*.modifiers.*.modifier_id' => ['required', Rule::exists('modifiers', 'id')->where('active', true)],
            'items.*.modifiers.*.quantity' => 'required|integer|min:1|max:999',
        ]);

        $restaurantTable = RestaurantTable::findOrFail($request->restaurant_table_id);

        if (Order::forTable($restaurantTable->number)->exists()) {
            return back()->with('error', "La {$restaurantTable->display_name} ya tiene una orden activa.");
        }

        $order = app(PosOperationService::class)->run(function () use ($request, $engine, $restaurantTable) {
            $restaurantTable = RestaurantTable::active()->lockForUpdate()->findOrFail($restaurantTable->id);
            if (Order::forTable($restaurantTable->number)->exists()) {
                throw ValidationException::withMessages(['restaurant_table_id' => 'La mesa ya tiene una orden activa. Actualiza el mapa.']);
            }

            $order = Order::create([
                'table_number' => $restaurantTable->number,
                'restaurant_table_id' => $restaurantTable->id,
                'waiter_id' => Auth::id(),
                'status' => 'pending',
            ]);

            // Crear detalles de la orden
            foreach ($request->items as $item) {
                $product = Product::active()->findOrFail($item['product_id']);
                $detail = OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'discount' => 0,
                    'subtotal' => Money::lineTotal($product->price, (int) $item['quantity']),
                    'comments' => $item['comments'] ?? null,
                ]);

                // Agregar modificadores si existen
                if (! empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $modifier = Modifier::active()->findOrFail($mod['modifier_id']);
                        $qty = $mod['quantity'] ?? 1;
                        OrderDetailModifier::create([
                            'order_detail_id' => $detail->id,
                            'modifier_id' => $modifier->id,
                            'quantity' => $qty,
                            'unit_price' => $modifier->price,
                            'subtotal' => Money::lineTotal($modifier->price, (int) $qty),
                        ]);
                    }
                }
            }

            $order->recalculateTotals();

            $order->load('details.product', 'details.modifiers');
            $engine->reserveForOrder($order);
            app(PosOperationService::class)->record($order, 'created', ['table_number' => $order->table_number]);

            return $order->fresh();
        });

        $printed = Bus::dispatchNow(new PrintPreticketJob($order));

        $redirectRoute = $request->routeIs('cashier.delivery.store') ? 'cashier.pos' : 'waiter.orders';

        return redirect()->route($redirectRoute)
            ->with($printed === false ? 'warning' : 'success', "Orden Mesa #{$order->table_number} creada. Inventario reservado. ".($printed === false ? 'No se pudo imprimir el pre-ticket; puedes reintentarlo sin crear otra venta.' : 'Pre-ticket enviado a impresora.'));
    }

    public function sendToKitchen(Order $order)
    {
        abort_unless($order->waiter_id === Auth::id() || Auth::user()->isProgrammer() || Auth::user()->isAdministrator(), 403);

        app(PosOperationService::class)->run(function () use ($order) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            if (! $order->isPending()) {
                throw ValidationException::withMessages(['order' => 'Esta orden ya no está pendiente.']);
            }
            $order->update([
                'status' => 'in_kitchen',
                'kitchen_sent_by' => Auth::id(),
                'kitchen_sent_at' => now(),
            ]);
            $order->details()->whereNull('kitchen_sent_at')->update(['kitchen_sent_at' => $order->kitchen_sent_at]);
        });
        $printed = Bus::dispatchNow(new PrintKitchenTicketJob($order->fresh(['waiter', 'kitchenSentBy'])));

        return back()->with($printed === false ? 'warning' : 'success', "Orden Mesa #{$order->table_number} enviada a cocina. ".($printed === false ? 'Falló la impresión; avisa a cocina y reimprime desde caja.' : 'Comanda enviada a impresora.'));
    }

    public function printPreticket(Order $order)
    {
        if ($order->waiter_id !== Auth::id() && ! Auth::user()->isProgrammer() && ! Auth::user()->isAdministrator()) {
            abort(403);
        }

        if (! in_array($order->status, ['pending', 'in_kitchen', 'ready'], true)) {
            return back()->with('error', 'No se puede imprimir pre-ticket de una orden cerrada.');
        }

        $printed = Bus::dispatchNow(new PrintPreticketJob($order->fresh(['details.product', 'details.modifiers.modifier', 'waiter', 'restaurantTable'])));

        return back()->with($printed === false ? 'warning' : 'success', $printed === false ? 'No se pudo imprimir. Revisa la impresora y reintenta.' : "Pre-ticket Mesa #{$order->table_number} enviado a impresora.");
    }

    private function ensureDeliveryTables(): void
    {
        foreach (range(1, 5) as $n) {
            RestaurantTable::firstOrCreate(
                ['number' => 900 + $n],
                [
                    'name' => "Domicilio {$n}",
                    'zone' => 'Domicilios',
                    'capacity' => 1,
                    'grid_row' => 0,
                    'grid_col' => $n,
                    'active' => true,
                    'sort_order' => 900 + $n,
                ]
            );
        }
    }
}
