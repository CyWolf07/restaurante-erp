<?php

namespace App\Services;

use App\Events\SupplyStockLow;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Supply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryEngine
{
    /**
     * 1ª confirmación (mesero crea la venta): reserva y descuenta stock provisionalmente.
     */
    public function reserveForOrder(Order $order): void
    {
        if ($order->inventory_reserved_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            $this->deductForOrder($order, 'sale_reserved');
            $order->update(['inventory_reserved_at' => now()]);
        });
    }

    /**
     * 2ª confirmación (cajero cierra venta): confirma consumo definitivo.
     */
    public function confirmForOrder(Order $order): void
    {
        if ($order->inventory_confirmed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            if (! $order->inventory_reserved_at) {
                $this->deductForOrder($order, 'sale_confirmed');
            } else {
                InventoryLog::where('order_id', $order->id)
                    ->where('type', 'sale_reserved')
                    ->whereNull('reversed_at')
                    ->update(['type' => 'sale_confirmed']);
            }

            $order->update(['inventory_confirmed_at' => now(), 'inventory_cost_captured_at' => now()]);
        });
    }

    /**
     * Revierte reserva si se cancela la orden antes del cobro.
     */
    public function reverseReservation(Order $order): void
    {
        if (! $order->inventory_reserved_at || $order->inventory_confirmed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            $sentDetails = $order->details()->whereNotNull('kitchen_sent_at')->pluck('id')->all();
            $logs = InventoryLog::where('order_id', $order->id)
                ->where('type', 'sale_reserved')
                ->whereNull('reversed_at')
                ->get();

            if ($logs->isEmpty()) {
                $logs = InventoryLog::whereIn('order_detail_id', $order->details()->pluck('id'))
                    ->where('type', 'sale_reserved')
                    ->whereNull('reversed_at')
                    ->get();
            }

            foreach ($logs as $log) {
                if (in_array($log->order_detail_id, $sentDetails, true) || (! $log->order_detail_id && $order->kitchen_sent_at)) {
                    $log->update(['type' => 'manual_waste', 'description' => 'Merma por cancelación de plato enviado a cocina: '.$order->id]);

                    continue;
                }
                $supply = Supply::where('id', $log->supply_id)->lockForUpdate()->first();
                if (! $supply) {
                    continue;
                }
                $qty = abs((float) $log->quantity);
                $newStock = (float) $supply->current_stock + $qty;
                $supply->update(['current_stock' => $newStock]);

                InventoryLog::create([
                    'supply_id' => $supply->id,
                    'type' => 'sale_reversal',
                    'quantity' => $qty,
                    'stock_after' => $newStock,
                    'user_id' => Auth::id() ?? $order->waiter_id,
                    'order_id' => $order->id,
                    'description' => "Reversión reserva orden mesa {$order->table_number}",
                    'created_at' => now(),
                ]);
                $log->update(['reversed_at' => now()]);
            }

            $order->update(['inventory_reserved_at' => null]);
        });
    }

    public function reverseReservationForDetail(OrderDetail $detail): void
    {
        $order = $detail->order;

        if ($detail->kitchen_sent_at && $order && ! $order->inventory_confirmed_at) {
            InventoryLog::where('order_detail_id', $detail->id)->where('type', 'sale_reserved')->whereNull('reversed_at')
                ->update(['type' => 'manual_waste', 'description' => 'Merma por retiro de plato enviado a cocina: '.$detail->id]);

            return;
        }

        if (! $order || ! $order->inventory_reserved_at || $order->inventory_confirmed_at) {
            return;
        }

        DB::transaction(function () use ($detail, $order) {
            $logs = InventoryLog::where('order_detail_id', $detail->id)
                ->where('type', 'sale_reserved')
                ->whereNull('reversed_at')
                ->get();

            foreach ($logs as $log) {
                $supply = Supply::where('id', $log->supply_id)->lockForUpdate()->first();
                if (! $supply) {
                    continue;
                }

                $qty = abs((float) $log->quantity);
                $newStock = (float) $supply->current_stock + $qty;
                $supply->update(['current_stock' => $newStock]);

                InventoryLog::create([
                    'supply_id' => $supply->id,
                    'type' => 'sale_reversal',
                    'quantity' => $qty,
                    'stock_after' => $newStock,
                    'user_id' => Auth::id() ?? $order->waiter_id,
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                    'description' => "Reversion plato {$detail->product?->name} mesa {$order->table_number}",
                    'created_at' => now(),
                ]);
                $log->update(['reversed_at' => now()]);
            }
        });
    }

    public function reserveForDetail(OrderDetail $detail): void
    {
        $order = $detail->order;

        if (! $order || $order->inventory_confirmed_at) {
            return;
        }

        if (! $order->inventory_reserved_at) {
            $this->reserveForOrder($order->fresh(['details.product.recipes.supply', 'details.modifiers.modifier.supply']));

            return;
        }

        DB::transaction(function () use ($detail, $order) {
            $detail->loadMissing([
                'product.recipes.supply',
                'modifiers.modifier.supply',
            ]);

            $this->deductRecipeIngredients($detail, $order, 'sale_reserved');
            $this->deductModifierIngredients($detail, $order, 'sale_reserved');
        });
    }

    /** @deprecated Usar reserveForOrder en creación y confirmForOrder al cobrar. */
    public function deductForOrder(Order $order, string $type = 'sale_consumption'): void
    {
        DB::transaction(function () use ($order, $type) {
            $order->load([
                'details.product.recipes.supply',
                'details.modifiers.modifier.supply',
            ]);

            foreach ($order->details as $detail) {
                $this->deductRecipeIngredients($detail, $order, $type);
                $this->deductModifierIngredients($detail, $order, $type);
            }
        });
    }

    private function deductRecipeIngredients($detail, Order $order, string $type): void
    {
        if (! $detail->product?->recipes) {
            return;
        }

        foreach ($detail->product->recipes as $recipe) {
            $supply = $recipe->supply;
            if (! $supply) {
                continue;
            }

            $totalDeduction = $recipe->quantity_required * $detail->quantity;
            $this->performDeduction(
                supply: $supply,
                quantity: $totalDeduction,
                type: $type,
                userId: Auth::id() ?? $order->waiter_id,
                orderId: $order->id,
                orderDetailId: $detail->id,
                description: "[{$type}] {$detail->quantity}x {$detail->product->name} — {$supply->name}"
            );
        }
    }

    private function deductModifierIngredients($detail, Order $order, string $type): void
    {
        foreach ($detail->modifiers ?? [] as $orderModifier) {
            $modifier = $orderModifier->modifier;
            if (! $modifier?->affectsInventory()) {
                continue;
            }

            $supply = $modifier->supply;
            if (! $supply) {
                continue;
            }

            $totalDeduction = $modifier->extra_quantity * $orderModifier->quantity;
            $this->performDeduction(
                supply: $supply,
                quantity: $totalDeduction,
                type: $type,
                userId: Auth::id() ?? $order->waiter_id,
                orderId: $order->id,
                orderDetailId: $detail->id,
                description: "[{$type}] Modificador {$modifier->name}"
            );
        }
    }

    private function performDeduction(
        Supply $supply,
        float $quantity,
        string $type,
        string $userId,
        string $orderId,
        ?string $orderDetailId,
        string $description
    ): void {
        $lockedSupply = Supply::where('id', $supply->id)->lockForUpdate()->first();

        if (! $lockedSupply) {
            throw new \RuntimeException("Insumo no encontrado: {$supply->id}");
        }

        $newStock = (float) $lockedSupply->current_stock - $quantity;
        if ($newStock < -0.0000001) {
            throw ValidationException::withMessages(['inventory' => "Existencias insuficientes de {$lockedSupply->name}. No se registró la operación."]);
        }
        $newStock = max(0, $newStock);
        $lockedSupply->update(['current_stock' => $newStock]);

        InventoryLog::create([
            'supply_id' => $lockedSupply->id,
            'type' => $type,
            'quantity' => -$quantity,
            'stock_after' => $newStock,
            'unit_cost' => $lockedSupply->cost_per_unit,
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_detail_id' => $orderDetailId,
            'description' => $description,
            'created_at' => now(),
        ]);

        if ($newStock <= $lockedSupply->min_stock) {
            event(new SupplyStockLow($lockedSupply));
        }
    }

    public function registerPurchase(Supply $supply, float $quantity, string $userId, ?string $description = null, ?string $operationKey = null, ?string $unitValue = null): void
    {
        $this->validateQuantity($quantity);
        app(InventoryPurchaseService::class)->registerQuick($supply, $quantity, $userId, $description, $operationKey, $unitValue);
    }

    public function registerWaste(Supply $supply, float $quantity, string $userId, string $reason, ?string $operationKey = null): void
    {
        $this->validateQuantity($quantity);
        app(PosOperationService::class)->run(function () use ($supply, $quantity, $userId, $reason, $operationKey) {
            $requests = app(InventoryRequestService::class);
            $metadata = $requests->metadata($operationKey, $userId, ['type' => 'manual_waste', 'supply_id' => $supply->id, 'quantity' => $quantity, 'reason' => $reason]);
            if ($requests->existing($metadata)) {
                return;
            }
            $lockedSupply = Supply::where('id', $supply->id)->lockForUpdate()->first();
            $newStock = (float) $lockedSupply->current_stock - $quantity;
            if ($newStock < 0) {
                throw ValidationException::withMessages(['quantity' => 'La merma supera las existencias disponibles.']);
            }
            $lockedSupply->update(['current_stock' => $newStock]);

            InventoryLog::create($metadata + [
                'supply_id' => $lockedSupply->id,
                'type' => 'manual_waste',
                'unit_cost' => $lockedSupply->cost_per_unit,
                'quantity' => -$quantity,
                'stock_after' => $newStock,
                'user_id' => $userId,
                'description' => "Merma: {$reason}",
                'created_at' => now(),
            ]);

            if ($newStock <= $lockedSupply->min_stock) {
                event(new SupplyStockLow($lockedSupply));
            }
        }, false);
    }

    private function validateQuantity(float $quantity): void
    {
        if (! is_finite($quantity) || $quantity < 0.0001) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser positiva.']);
        }
    }
}
