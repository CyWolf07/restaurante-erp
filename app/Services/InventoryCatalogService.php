<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Supply;
use Illuminate\Validation\ValidationException;

class InventoryCatalogService
{
    public function create(array $data, string $userId): Supply
    {
        return app(PosOperationService::class)->run(function () use ($data, $userId) {
            $supply = Supply::create($data);
            InventoryLog::create([
                'supply_id' => $supply->id, 'type' => 'programmer_adjustment',
                'quantity' => $supply->current_stock ?? 0, 'stock_after' => $supply->current_stock ?? 0,
                'unit_cost' => $supply->cost_per_unit ?? 0,
                'user_id' => $userId, 'description' => 'Saldo inicial registrado al crear el insumo',
                'created_at' => now(),
            ]);

            return $supply;
        }, false);
    }

    public function update(Supply $supply, array $data): void
    {
        app(PosOperationService::class)->run(function () use ($supply, $data) {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('current_stock', $data)
                && abs((float) $data['current_stock'] - (float) $supply->current_stock) > 0.00001) {
                throw ValidationException::withMessages(['current_stock' => 'Registra un ajuste con motivo para cambiar las existencias.']);
            }
            unset($data['current_stock']);
            if (isset($data['unit_type']) && $data['unit_type'] !== $supply->unit_type
                && ($supply->inventoryLogs()->exists() || $supply->recipes()->exists() || (float) $supply->current_stock !== 0.0)) {
                throw ValidationException::withMessages(['unit_type' => 'La unidad tiene historial o recetas. Crea un artículo con la nueva unidad.']);
            }
            $before = $supply->only(array_keys($data));
            $supply->update($data);
            if ($supply->wasChanged()) {
                app(AuditService::class)->record('supply', $supply->id, 'catalog_updated', ['before' => $before, 'after' => $supply->only(array_keys($data))]);
            }
        }, false);
    }

    public function adjust(Supply $supply, float $quantity, float $expectedStock, string $reason, string $userId): void
    {
        app(PosOperationService::class)->run(function () use ($supply, $quantity, $expectedStock, $reason, $userId) {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            if (abs((float) $supply->current_stock - $expectedStock) > 0.00001) {
                throw ValidationException::withMessages(['adjustment' => 'El saldo cambió. Actualiza la pantalla antes de registrar el ajuste.']);
            }
            if (! is_finite($quantity) || abs($quantity) < 0.0001 || mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['adjustment' => 'Indica una cantidad distinta de cero y un motivo de al menos cinco caracteres.']);
            }
            $newStock = round($expectedStock + $quantity, 4);
            if ($newStock < 0) {
                throw ValidationException::withMessages(['adjustment' => 'El ajuste no puede dejar existencias disponibles negativas.']);
            }
            $supply->update(['current_stock' => $newStock]);
            InventoryLog::create([
                'supply_id' => $supply->id, 'type' => 'programmer_adjustment',
                'quantity' => $quantity, 'stock_after' => $newStock, 'unit_cost' => $supply->cost_per_unit,
                'user_id' => $userId, 'description' => 'Ajuste autorizado: '.trim($reason), 'created_at' => now(),
            ]);
        }, false);
    }
}
