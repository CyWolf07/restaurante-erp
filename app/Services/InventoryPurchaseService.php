<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\InventoryPurchase;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryPurchaseService
{
    public function register(array $data, User $user): InventoryPurchase
    {
        return DB::transaction(function () use ($data, $user) {
            $supply = Supply::where('code', $data['code'])->firstOrFail();
            $quantity = (float) $data['quantity'];
            $unitValue = (float) $data['unit_value'];
            $totalValue = round($quantity * $unitValue, 2);

            $locked = Supply::where('id', $supply->id)->lockForUpdate()->first();
            $newStock = (float) $locked->current_stock + $quantity;
            $locked->update([
                'current_stock' => $newStock,
                'cost_per_unit' => $unitValue > 0 ? $unitValue : $locked->cost_per_unit,
            ]);

            $log = InventoryLog::create([
                'supply_id'   => $locked->id,
                'type'        => 'purchase_entry',
                'quantity'    => $quantity,
                'stock_after' => $newStock,
                'user_id'     => $user->id,
                'description' => "Compra [{$data['adjustment_type']}] {$quantity} {$locked->unit_label} — Factura: " . ($data['invoice_number'] ?? 'N/A'),
                'created_at'  => now(),
            ]);

            return InventoryPurchase::create([
                'supply_id'        => $locked->id,
                'code'             => $data['code'],
                'supplier'         => $data['supplier'],
                'purchase_date'    => $data['purchase_date'],
                'adjustment_type'  => $data['adjustment_type'],
                'quantity'         => $quantity,
                'unit_value'       => $unitValue,
                'total_value'      => $totalValue,
                'invoice_number'   => $data['invoice_number'] ?? null,
                'point'            => $data['point'],
                'user_id'          => $user->id,
                'inventory_log_id' => $log->id,
            ]);
        });
    }
}
