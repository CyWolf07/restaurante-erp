<?php

namespace App\Http\Controllers;

use App\Jobs\PrintKitchenTicketJob;
use App\Jobs\PrintReceiptJob;
use App\Models\CashierCashCount;
use App\Models\CashierDailyClosure;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailModifier;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\AuditService;
use App\Services\CashierDailyClosureService;
use App\Services\FiscalDocumentService;
use App\Services\InventoryEngine;
use App\Services\PosOperationService;
use App\Services\ReportZService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function pos(CashierDailyClosureService $cashierClosures, FiscalDocumentService $fiscalDocuments)
    {
        $tables = RestaurantTable::active()->ordered()->get()->map(function (RestaurantTable $table) {
            $order = $table->activeOrder();
            $isDelivery = $table->zone === 'Domicilios' || str_starts_with((string) $table->name, 'Domicilio');
            $shortLabel = $table->number;
            if ($isDelivery && preg_match('/(\d+)/', (string) $table->name, $m)) {
                $shortLabel = 'D'.$m[1];
            }

            return [
                'id' => $table->id,
                'number' => $table->number,
                'name' => $table->display_name,
                'short_label' => (string) $shortLabel,
                'zone' => $table->zone,
                'order' => $order,
                'status' => $order ? $order->status : 'free',
                'color' => $table->status_color,
            ];
        });

        $cashierClosureSummary = $cashierClosures->dailySummary();
        $fiscalPendingSummary = $fiscalDocuments->unsentSummary();
        $cashierClosure = CashierDailyClosure::whereDate('fiscal_date', today())->first();
        $cashierClosureExists = $cashierClosure !== null;

        return view('cashier.pos', compact('tables', 'cashierClosureSummary', 'cashierClosure', 'cashierClosureExists', 'fiscalPendingSummary'));
    }

    public function tableDetail(int $table)
    {
        $restaurantTable = RestaurantTable::where('number', $table)->firstOrFail();
        $order = Order::forTable($table)
            ->with(['details.product', 'details.modifiers.modifier', 'waiter'])
            ->latest()
            ->first();
        $products = Product::active()->with('category.modifiers')->orderBy('name')->get();

        // Construir mapa de opciones por producto para el JS
        $productOptions = [];
        foreach ($products as $product) {
            $options = $product->getEffectiveOptions();
            if ($options->isNotEmpty()) {
                $productOptions[$product->id] = $options->map(fn ($group) => $group->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                ])->values());
            }
        }

        $availableTables = RestaurantTable::active()->ordered()
            ->where('id', '!=', $restaurantTable->id)
            ->whereNotIn('number', Order::active()->select('table_number'))
            ->get();

        return view('cashier.table-detail', compact('order', 'table', 'restaurantTable', 'products', 'productOptions', 'availableTables'));
    }

    public function markReady(Order $order)
    {
        app(PosOperationService::class)->run(function () use ($order) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            if (! $order->isInKitchen()) {
                throw ValidationException::withMessages(['order' => 'La orden no está en cocina.']);
            }
            $order->update(['status' => 'ready']);
            app(PosOperationService::class)->record($order, 'ready', ['source' => 'kitchen_or_cashier']);
        });

        return back()->with('success', 'Orden marcada como lista.');
    }

    public function reprint(Request $request, Order $order)
    {
        $data = $request->validate(['purpose' => ['required', Rule::in(['kitchen', 'receipt'])]]);
        if (($data['purpose'] === 'receipt' && ! $order->isPaid()) || ($data['purpose'] === 'kitchen' && ! $order->kitchen_sent_at)) {
            throw ValidationException::withMessages(['purpose' => 'No corresponde reimprimir ese documento para esta orden.']);
        }
        $job = $data['purpose'] === 'receipt' ? new PrintReceiptJob($order) : new PrintKitchenTicketJob($order);
        $printed = Bus::dispatchNow($job);
        app(PosOperationService::class)->record($order, 'reprint_requested', ['purpose' => $data['purpose'], 'sent_to_printer' => $printed === true]);

        return back()->with($printed === false ? 'warning' : 'success', $printed === false ? 'No se pudo imprimir. No se modificó la venta.' : 'Reimpresión enviada a impresora; no crea otra venta.');
    }

    public function sendToKitchen(Order $order)
    {
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

        $printed = Bus::dispatchNow(new PrintKitchenTicketJob($order->fresh(['waiter', 'kitchenSentBy', 'restaurantTable'])));

        return back()->with($printed === false ? 'warning' : 'success', "Orden Mesa #{$order->table_number} enviada a cocina. ".($printed === false ? 'Falló la impresión; avisa a cocina y reintenta la impresión.' : 'Comanda enviada a impresora.'));
    }

    public function destroyOrderDetail(Request $request, OrderDetail $detail, InventoryEngine $engine)
    {
        $order = $detail->order()->with('details')->firstOrFail();

        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede eliminar un plato de una orden cerrada.');
        }

        if (! in_array(Auth::user()->role, ['cashier', 'administrator', 'programmer'], true)) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        app(PosOperationService::class)->run(function () use ($detail, $engine, $order, $data) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            $detail = $order->details()->findOrFail($detail->id);
            app(PosOperationService::class)->record($order, 'detail_removed', [
                'detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'quantity' => $detail->quantity,
                'reason' => $data['reason'],
            ]);
            $engine->reverseReservationForDetail($detail->load('product'));
            $detail->delete();

            if ($order->details()->count() === 0) {
                $order->update([
                    'status' => 'cancelled',
                    'cashier_id' => Auth::id(),
                    'cancellation_reason' => $data['reason'],
                    'subtotal' => 0,
                    'tax' => 0,
                    'total' => 0,
                ]);

                return;
            }

            $order->refresh()->recalculateTotals();
        });

        return redirect()->route('cashier.table-detail', $order->table_number)
            ->with('success', 'Plato eliminado y totales recalculados.');
    }

    public function storeOrderDetail(Request $request, Order $order, InventoryEngine $engine)
    {
        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede agregar un plato a una orden cerrada.');
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
            'discount' => 'nullable|numeric|decimal:0,2|min:0|max:999999999.99',
            'comments' => 'nullable|string|max:1000',
            'modifiers' => 'nullable|array',
            'modifiers.*.modifier_id' => ['required', Rule::exists('modifiers', 'id')->where('active', true)],
            'modifiers.*.quantity' => 'required|integer|min:1|max:999',
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $quantity = (int) $data['quantity'];
        $grossSubtotal = Money::lineTotal($product->price, $quantity);
        $discount = (string) ($data['discount'] ?? '0');
        Money::lineTotal($product->price, $quantity, $discount);

        app(PosOperationService::class)->run(function () use ($order, $product, $quantity, $discount, $data, $engine) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            $detail = OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'discount' => $discount,
                'subtotal' => Money::lineTotal($product->price, $quantity, $discount),
                'comments' => $data['comments'] ?? null,
            ]);

            if (! empty($data['modifiers'])) {
                foreach ($data['modifiers'] as $mod) {
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

            if ($order->isInKitchen() || $order->isReady()) {
                $order->update(['status' => 'pending']);
            }

            $order->refresh()->recalculateTotals();
            $engine->reserveForDetail($detail->fresh(['product.recipes.supply', 'modifiers.modifier.supply']));
            app(PosOperationService::class)->record($order, 'detail_added', ['detail_id' => $detail->id, 'quantity' => $quantity]);
        });

        return redirect()->route('cashier.table-detail', $order->table_number)
            ->with('success', 'Plato agregado y totales recalculados.');
    }

    public function updateOrderDetail(Request $request, OrderDetail $detail, InventoryEngine $engine)
    {
        $order = $detail->order()->firstOrFail();

        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede editar un plato de una orden cerrada.');
        }

        if (! in_array(Auth::user()->role, ['cashier', 'administrator', 'programmer'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
            'discount' => 'nullable|numeric|decimal:0,2|min:0|max:999999999.99',
            'comments' => 'nullable|string|max:1000',
        ]);

        $product = Product::active()->findOrFail($data['product_id']);
        $quantity = (int) $data['quantity'];
        $discount = (string) ($data['discount'] ?? '0');

        app(PosOperationService::class)->run(function () use ($detail, $engine, $order, $product, $quantity, $discount, $data) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            $detail = $order->details()->findOrFail($detail->id);
            $before = $detail->only(['product_id', 'quantity', 'discount', 'comments']);
            $inventoryChanged = $detail->product_id !== $product->id || (int) $detail->quantity !== $quantity;
            if ($inventoryChanged && $detail->kitchen_sent_at) {
                throw ValidationException::withMessages(['quantity' => 'El plato ya fue enviado a cocina. Retíralo con motivo (se registra merma) y agrega el reemplazo; no se repone inventario automáticamente.']);
            }
            $unitPrice = $detail->product_id === $product->id ? $detail->unit_price : $product->price;
            if ($inventoryChanged) {
                $engine->reverseReservationForDetail($detail->load('product'));
            }

            $detail->update([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_type' => $detail->product_id === $product->id ? $detail->tax_type : ($product->tax_type ?? 'configured'),
                'tax_rate' => $detail->product_id === $product->id ? $detail->tax_rate : ($product->tax_rate ?? config('app.tax_rate', 0)),
                'discount' => $discount,
                'subtotal' => Money::lineTotal($unitPrice, $quantity, $discount),
                'comments' => $data['comments'] ?? null,
            ]);

            if ($detail->wasChanged('product_id')) {
                $detail->modifiers()->delete();
            }

            if ($inventoryChanged && ($order->isInKitchen() || $order->isReady())) {
                $order->update(['status' => 'pending']);
            }

            $order->refresh()->recalculateTotals();
            if ($inventoryChanged) {
                $engine->reserveForDetail($detail->fresh(['product.recipes.supply', 'modifiers.modifier.supply']));
            }
            app(PosOperationService::class)->record($order, 'detail_updated', [
                'detail_id' => $detail->id,
                'before' => $before,
                'after' => $detail->only(['product_id', 'quantity', 'discount', 'comments']),
            ]);
        });

        return redirect()->route('cashier.table-detail', $order->table_number)
            ->with('success', 'Plato editado y totales recalculados.');
    }

    public function payOrder(Request $request, Order $order, InventoryEngine $engine)
    {
        if ($order->isPaid() || $order->isCancelled()) {
            return back()->with('error', 'Esta orden ya fue procesada.');
        }

        $request->mergeIfMissing(['payment_method' => 'cash']);
        $data = $request->validate([
            'expected_total' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer', 'mixed', 'other'])],
            'payments' => 'nullable|array',
            'payments.*' => 'numeric|decimal:0,2|min:0',
        ]);
        app(PosOperationService::class)->run(function () use ($order, $engine, $data) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            if (! $order->details()->exists()) {
                throw ValidationException::withMessages(['order' => 'No se puede cobrar una orden sin productos.']);
            }
            $order->recalculateTotals();
            if ((int) round((float) $order->total * 100) !== (int) round((float) $data['expected_total'] * 100)) {
                throw ValidationException::withMessages(['expected_total' => 'El total cambió. Actualiza la pantalla y confirma el nuevo valor.']);
            }
            $engine->confirmForOrder($order);
            $payments = $data['payment_method'] === 'mixed' ? ($data['payments'] ?? []) : [$data['payment_method'] => $order->total];
            $sum = BigDecimal::zero();
            foreach ($payments as $method => $amount) {
                if (! in_array($method, ['cash', 'card', 'transfer', 'other'], true)) {
                    throw ValidationException::withMessages(['payments' => 'Medio de pago inválido.']);
                }
                $sum = $sum->plus((string) $amount);
            }
            if (! $sum->isEqualTo($order->total)) {
                throw ValidationException::withMessages(['payments' => 'Los pagos deben sumar exactamente el total de la cuenta.']);
            }
            $order->update(['status' => 'paid', 'cashier_id' => Auth::id(), 'payment_breakdown' => $payments]);
            app(FiscalDocumentService::class)->createDraftForPaidOrder($order->fresh(), Auth::user(), $data['payment_method']);
            app(PosOperationService::class)->record($order, 'paid', ['total' => $order->total]);
        });
        $order->refresh();

        $printed = true;
        if ($request->boolean('print_receipt', true)) {
            $printed = Bus::dispatchNow(new PrintReceiptJob($order->fresh()));
        }

        return redirect()->route('cashier.pos')
            ->with($printed === false ? 'warning' : 'success', 'Orden Mesa #'.$order->table_number.' cobrada: '.cop($order->total).'. Borrador fiscal creado para revisión; no ha sido transmitido.'.($printed === false ? ' Falló la impresión del recibo. No vuelvas a cobrar esta venta.' : ''));
    }

    public function cancelOrder(Request $request, Order $order, InventoryEngine $engine)
    {
        if ($order->isPaid() || $order->isCancelled() || $order->isLocked()) {
            return back()->with('error', 'No se puede cancelar esta orden.');
        }
        $request->validate(['reason' => 'required|string|min:5|max:1000']);
        app(PosOperationService::class)->run(function () use ($order, $engine, $request) {
            $order = app(PosOperationService::class)->editableOrder($order->id);
            $engine->reverseReservation($order);
            $order->update([
                'status' => 'cancelled',
                'cashier_id' => Auth::id(),
                'cancellation_reason' => $request->reason,
            ]);
            app(PosOperationService::class)->record($order, 'cancelled', ['reason' => $request->reason]);
        });

        return redirect()->route('cashier.pos')
            ->with('success', "Orden Mesa #{$order->table_number} cancelada. Los ingredientes enviados a cocina se registran como merma; solo las reservas no enviadas se restituyen.");
    }

    public function transferOrder(Request $request, Order $order, PosOperationService $operations)
    {
        $data = $request->validate([
            'restaurant_table_id' => ['required', Rule::exists('restaurant_tables', 'id')->where('active', true)],
        ]);

        $destination = $operations->run(function () use ($order, $data, $operations) {
            $order = $operations->editableOrder($order->id);
            $destination = RestaurantTable::active()->lockForUpdate()->findOrFail($data['restaurant_table_id']);
            if (Order::forTable($destination->number)->exists()) {
                throw ValidationException::withMessages(['restaurant_table_id' => 'La mesa de destino está ocupada. Elige otra mesa.']);
            }

            $origin = $order->table_number;
            $order->update([
                'restaurant_table_id' => $destination->id,
                'table_number' => $destination->number,
            ]);
            $operations->record($order, 'transferred', ['from' => $origin, 'to' => $destination->number]);

            return $destination;
        });

        return redirect()->route('cashier.table-detail', $destination->number)
            ->with('success', "Orden trasladada a {$destination->display_name}. Avisa a cocina si ya recibió la comanda.");
    }

    public function generateReportZ(ReportZService $service)
    {
        try {
            $report = $service->generateReportZ(Auth::user());

            return redirect()->route('cashier.pos')
                ->with('success', "Informe Z generado para {$report->fiscal_date->format('d/m/Y')}.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function closeCashierDay(Request $request, CashierDailyClosureService $service)
    {
        $data = $request->validate([
            'expenses' => 'nullable|array',
            'expenses.*.concept' => 'nullable|string|max:120',
            'expenses.*.amount' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        try {
            $closure = $service->closeDay(Auth::user(), $data['expenses'] ?? []);

            return redirect()->route('cashier.cash-closure.pdf', $closure)
                ->with('success', 'Cierre de caja generado correctamente.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cashierClosurePdf(CashierDailyClosure $closure)
    {
        try {
            if (! $closure->pdf_local_path || ! file_exists($closure->pdf_local_path)) {
                $closure->update(['pdf_local_path' => app(CashierDailyClosureService::class)->generatePdf($closure->load('cashier'))]);
            }

            return response()->file($closure->pdf_local_path);
        } catch (\Throwable $error) {
            report($error);

            return redirect()->route('cashier.pos')->with('error', 'No se pudo recuperar el PDF. El cierre sigue guardado; revisa el almacenamiento y vuelve a intentarlo.');
        }
    }

    public function cashCount()
    {
        $denominations = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50];
        $cashCount = CashierCashCount::whereDate('fiscal_date', today())->first();

        return view('cashier.cash-count', compact('denominations', 'cashCount'));
    }

    public function storeCashCount(Request $request)
    {
        $data = $request->validate([
            'base' => 'nullable|array',
            'base.*' => 'nullable|integer|min:0|max:99999',
            'change' => 'nullable|array',
            'change.*' => 'nullable|integer|min:0|max:99999',
            'sales' => 'nullable|array',
            'sales.*' => 'nullable|integer|min:0|max:99999',
            'notes' => 'nullable|string|max:1000',
        ]);

        $base = $this->normalizeCashCounts($data['base'] ?? []);
        $change = $this->normalizeCashCounts($data['change'] ?? []);
        $sales = $this->normalizeCashCounts($data['sales'] ?? []);

        $baseTotal = $this->cashCountTotal($base);
        $changeTotal = $this->cashCountTotal($change);
        $salesTotal = $this->cashCountTotal($sales);
        if ($baseTotal + $changeTotal + $salesTotal > 9999999999.99) {
            throw ValidationException::withMessages(['sales' => 'El total del arqueo supera el límite admitido. Revisa las cantidades de billetes y monedas.']);
        }

        app(PosOperationService::class)->run(function () use ($base, $change, $sales, $baseTotal, $changeTotal, $salesTotal, $data) {
            if (CashierDailyClosure::whereDate('fiscal_date', today())->exists()) {
                throw ValidationException::withMessages(['order' => 'El arqueo pertenece a una caja cerrada y no se puede modificar.']);
            }
            $previous = CashierCashCount::whereDate('fiscal_date', today())->first();
            $count = CashierCashCount::whereDate('fiscal_date', today())->updateOrCreate(
                [],
                [
                    'fiscal_date' => today()->toDateString(),
                    'cashier_id' => Auth::id(),
                    'base_counts' => $base,
                    'change_counts' => $change,
                    'sales_counts' => $sales,
                    'base_total' => $baseTotal,
                    'change_total' => $changeTotal,
                    'sales_total' => $salesTotal,
                    'declared_cash_total' => $baseTotal + $changeTotal + $salesTotal,
                    'notes' => $data['notes'] ?? null,
                ]
            );
            app(AuditService::class)->record('cash_count', $count->id, 'cash_count_saved', [
                'before' => $previous?->only(['base_counts', 'change_counts', 'sales_counts', 'declared_cash_total', 'notes']),
                'after' => $count->only(['base_counts', 'change_counts', 'sales_counts', 'declared_cash_total', 'notes']),
            ]);
        }, requireOpen: false);

        return back()->with('success', 'Arqueo de caja guardado correctamente.');
    }

    private function normalizeCashCounts(array $counts): array
    {
        $normalized = [];

        foreach ($counts as $denomination => $quantity) {
            if (! in_array((string) $denomination, ['100000', '50000', '20000', '10000', '5000', '2000', '1000', '500', '200', '100', '50'], true)) {
                throw ValidationException::withMessages(['sales' => 'Hay una denominación de efectivo no válida. Actualiza el formulario.']);
            }
            $denomination = (int) $denomination;
            $quantity = (int) ($quantity ?? 0);

            if (in_array($denomination, [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50], true) && $quantity >= 0) {
                $normalized[(string) $denomination] = $quantity;
            }
        }

        krsort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private function cashCountTotal(array $counts): int
    {
        return array_reduce(
            array_keys($counts),
            fn (int $total, string $denomination) => $total + ((int) $denomination * (int) $counts[$denomination]),
            0
        );
    }
}
