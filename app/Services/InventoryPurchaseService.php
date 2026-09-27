<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\InventoryPurchase;
use App\Models\Supply;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryPurchaseService
{
    public function register(array $data, User $user): InventoryPurchase
    {
        Validator::make($data, [
            'quantity' => 'required|numeric|decimal:0,4|min:0.0001|max:99999999.9999',
            'unit_value' => 'required|numeric|decimal:0,2|min:0|max:99999999.99',
            'supplier' => 'required|string|max:255',
            'purchase_date' => 'required|date_format:Y-m-d',
            'adjustment_type' => 'required|in:compra,bonificacion,inventario,reposicion',
            'point' => 'required|in:el_muelle,bocagrande,oficinas,bodega',
            'invoice_number' => 'nullable|string|max:100',
            'operation_key' => 'nullable|uuid',
        ])->validate();

        return app(PosOperationService::class)->run(function () use ($data, $user) {
            $payload = $data;
            unset($payload['operation_key']);
            $payload['quantity'] = (float) $data['quantity'];
            $payload['unit_value'] = (float) $data['unit_value'];
            $payload['type'] = 'purchase_entry';
            $requests = app(InventoryRequestService::class);
            $metadata = $requests->metadata($data['operation_key'] ?? null, $user->id, $payload);
            if ($existing = $requests->existing($metadata)) {
                return InventoryPurchase::where('inventory_log_id', $existing->id)->firstOrFail();
            }
            $supply = isset($data['supply_id'])
                ? Supply::findOrFail($data['supply_id'])
                : Supply::where('code', $data['code'])->firstOrFail();
            $quantity = BigDecimal::of((string) $data['quantity'])->toScale(4);
            $unitValue = BigDecimal::of((string) $data['unit_value'])->toScale(2);
            $totalValue = $quantity->multipliedBy($unitValue)->toScale(2, RoundingMode::HALF_UP);

            $locked = Supply::where('id', $supply->id)->lockForUpdate()->first();
            $newStock = BigDecimal::of($locked->current_stock)->plus($quantity);
            if ($newStock->isGreaterThan('99999999.9999') || $totalValue->isGreaterThan('9999999999.99')) {
                throw ValidationException::withMessages(['quantity' => 'La compra supera el límite admitido de cantidad o valor.']);
            }
            $locked->update([
                'current_stock' => (string) $newStock,
                'cost_per_unit' => $unitValue->isPositive() ? (string) $unitValue : $locked->cost_per_unit,
            ]);

            $log = InventoryLog::create($metadata + [
                'supply_id' => $locked->id,
                'type' => 'purchase_entry',
                'unit_cost' => (string) $unitValue,
                'quantity' => (string) $quantity,
                'stock_after' => (string) $newStock,
                'user_id' => $user->id,
                'description' => "Compra [{$data['adjustment_type']}] {$quantity} {$locked->unit_label} — Factura: ".($data['invoice_number'] ?? 'N/A'),
                'created_at' => now(),
            ]);

            return InventoryPurchase::create([
                'supply_id' => $locked->id,
                'code' => $data['code'],
                'supplier' => $data['supplier'],
                'purchase_date' => $data['purchase_date'],
                'adjustment_type' => $data['adjustment_type'],
                'quantity' => (string) $quantity,
                'unit_value' => (string) $unitValue,
                'total_value' => (string) $totalValue,
                'invoice_number' => $data['invoice_number'] ?? null,
                'point' => $data['point'],
                'user_id' => $user->id,
                'inventory_log_id' => $log->id,
            ]);
        }, false);
    }

    public function registerQuick(Supply $supply, float $quantity, string $userId, ?string $description, ?string $operationKey, ?string $unitValue = null): void
    {
        app(PosOperationService::class)->run(function () use ($supply, $quantity, $userId, $description, $operationKey, $unitValue) {
            $requests = app(InventoryRequestService::class);
            $payload = ['type' => 'supplier_purchase', 'supply_id' => $supply->id, 'quantity' => $quantity, 'description' => $description];
            if ($unitValue !== null) {
                $payload['unit_value'] = (string) BigDecimal::of($unitValue)->toScale(2);
            }
            $metadata = $requests->metadata($operationKey, $userId, $payload);
            if ($requests->existing($metadata)) {
                return;
            }
            $locked = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            $purchase = $this->register([
                'code' => $locked->code ?? $locked->id,
                'supply_id' => $locked->id,
                'quantity' => $quantity,
                'unit_value' => $unitValue ?? (string) BigDecimal::of($locked->cost_per_unit)->toScale(2, RoundingMode::HALF_UP),
                'supplier' => $locked->supplier ?: 'Sin proveedor registrado',
                'purchase_date' => today()->toDateString(),
                'adjustment_type' => 'compra',
                'point' => $locked->point ?: 'bodega',
            ], User::findOrFail($userId));
            InventoryLog::whereKey($purchase->inventory_log_id)->update($metadata + [
                'type' => 'supplier_purchase',
                'description' => $description ?: 'Compra rápida; consultar documento de compra.',
            ]);
        }, false);
    }
}
