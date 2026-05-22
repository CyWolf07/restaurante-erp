<?php

namespace App\Services;

use App\Events\SupplyStockLow;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Supply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            if (!$order->inventory_reserved_at) {
                $this->deductForOrder($order, 'sale_confirmed');
            } else {
                InventoryLog::where('order_id', $order->id)
                    ->where('type', 'sale_reserved')
                    ->update(['type' => 'sale_confirmed']);
            }

            $order->update(['inventory_confirmed_at' => now()]);
        });
    }

    /**
     * Revierte reserva si se cancela la orden antes del cobro.
     */
    public function reverseReservation(Order $order): void
    {
        if (!$order->inventory_reserved_at || $order->inventory_confirmed_at) {
            return;
        }

        DB::transaction(function () use ($order) {
            $logs = InventoryLog::where('order_id', $order->id)
                ->where('type', 'sale_reserved')
                ->get();

            if ($logs->isEmpty()) {
                $logs = InventoryLog::whereIn('order_detail_id', $order->details()->pluck('id'))
                    ->where('type', 'sale_reserved')
                    ->get();
            }

            foreach ($logs as $log) {
                $supply = Supply::where('id', $log->supply_id)->lockForUpdate()->first();
                if (!$supply) {
                    continue;
                }
                $qty = abs((float) $log->quantity);
                $newStock = (float) $supply->current_stock + $qty;
                $supply->update(['current_stock' => $newStock]);

                InventoryLog::create([
                    'supply_id'   => $supply->id,
                    'type'        => 'sale_reversal',
                    'quantity'    => $qty,
                    'stock_after' => $newStock,
                    'user_id'     => Auth::id() ?? $order->waiter_id,
                    'order_id'    => $order->id,
                    'description' => "Reversión reserva orden mesa {$order->table_number}",
                    'created_at'  => now(),
                ]);
            }

            $order->update(['inventory_reserved_at' => null]);
        });
    }

    public function reverseReservationForDetail(OrderDetail $detail): void
    {
        $order = $detail->order;

        if (!$order || !$order->inventory_reserved_at || $order->inventory_confirmed_at) {
            return;
        }

        DB::transaction(function () use ($detail, $order) {
            $logs = InventoryLog::where('order_detail_id', $detail->id)
                ->where('type', 'sale_reserved')
                ->get();

            foreach ($logs as $log) {
                $supply = Supply::where('id', $log->supply_id)->lockForUpdate()->first();
                if (!$supply) {
                    continue;
                }

                $qty = abs((float) $log->quantity);
                $newStock = (float) $supply->current_stock + $qty;
                $supply->update(['current_stock' => $newStock]);

                InventoryLog::create([
                    'supply_id'       => $supply->id,
                    'type'            => 'sale_reversal',
                    'quantity'        => $qty,
                    'stock_after'     => $newStock,
                    'user_id'         => Auth::id() ?? $order->waiter_id,
                    'order_id'        => $order->id,
                    'order_detail_id' => $detail->id,
                    'description'     => "Reversion plato {$detail->product?->name} mesa {$order->table_number}",
                    'created_at'      => now(),
                ]);
            }
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
        if (!$detail->product?->recipes) {
            return;
        }

        foreach ($detail->product->recipes as $recipe) {
            $supply = $recipe->supply;
            if (!$supply) {
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
            if (!$modifier?->affectsInventory()) {
                continue;
            }

            $supply = $modifier->supply;
            if (!$supply) {
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

        if (!$lockedSupply) {
            throw new \RuntimeException("Insumo no encontrado: {$supply->id}");
        }

        $newStock = (float) $lockedSupply->current_stock - $quantity;
        $lockedSupply->update(['current_stock' => $newStock]);

        InventoryLog::create([
            'supply_id'       => $lockedSupply->id,
            'type'            => $type,
            'quantity'        => -$quantity,
            'stock_after'     => $newStock,
            'user_id'         => $userId,
            'order_id'        => $orderId,
            'order_detail_id' => $orderDetailId,
            'description'     => $description,
            'created_at'      => now(),
        ]);

        if ($newStock <= $lockedSupply->min_stock) {
            event(new SupplyStockLow($lockedSupply));
        }
    }

    public function registerPurchase(Supply $supply, float $quantity, string $userId, ?string $description = null): void
    {
        DB::transaction(function () use ($supply, $quantity, $userId, $description) {
            $lockedSupply = Supply::where('id', $supply->id)->lockForUpdate()->first();
            $newStock = (float) $lockedSupply->current_stock + $quantity;
            $lockedSupply->update(['current_stock' => $newStock]);

            InventoryLog::create([
                'supply_id'   => $lockedSupply->id,
                'type'        => 'supplier_purchase',
                'quantity'    => $quantity,
                'stock_after' => $newStock,
                'user_id'     => $userId,
                'description' => $description ?? "Compra: +{$quantity}{$lockedSupply->unit_label}",
                'created_at'  => now(),
            ]);
        });
    }

    public function registerWaste(Supply $supply, float $quantity, string $userId, string $reason): void
    {
        DB::transaction(function () use ($supply, $quantity, $userId, $reason) {
            $lockedSupply = Supply::where('id', $supply->id)->lockForUpdate()->first();
            $newStock = (float) $lockedSupply->current_stock - $quantity;
            $lockedSupply->update(['current_stock' => $newStock]);

            InventoryLog::create([
                'supply_id'   => $lockedSupply->id,
                'type'        => 'manual_waste',
                'quantity'    => -$quantity,
                'stock_after' => $newStock,
                'user_id'     => $userId,
                'description' => "Merma: {$reason}",
                'created_at'  => now(),
            ]);

            if ($newStock <= $lockedSupply->min_stock) {
                event(new SupplyStockLow($lockedSupply));
            }
        });
    }
}
