<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\InventoryPurchase;
use App\Models\Supply;
use Illuminate\Support\Collection;

/**
 * Métricas del CONTROL DE INVENTARIOS MANUAL (equivalente Excel).
 *
 * ENTRADA / V·ENTRADA  ← Compras (ingresos) — inventory_purchases
 * SALIDA               ← Ventas — inventory_logs (consumo confirmado)
 * V/SALIDA             ← (V/ENTRADA / ENTRADA) × SALIDA
 * STOCK                ← saldo operativo disponible (incluye reservas)
 * FÍSICO               ← disponible + reservas vigentes
 * V/STOCK              ← físico × costo actual (valor orientativo)
 * PRECIO               ← P.V.P del producto
 * V.C.U.               ← V/STOCK ÷ STOCK (0 si stock = 0)
 * INVENTARIO           ← conteo físico manual (physical_count)
 * DIFERENCIA           ← STOCK − INVENTARIO
 */
class CommerceInventoryMetricsService
{
    private const SALE_EXIT_TYPES = ['sale_confirmed', 'sale_consumption'];

    public function attachToSupplies(Collection $supplies): void
    {
        $ids = $supplies->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $entradas = InventoryPurchase::query()
            ->whereIn('supply_id', $ids)
            ->selectRaw('supply_id, COALESCE(SUM(quantity), 0) as entrada, COALESCE(SUM(total_value), 0) as v_entrada')
            ->groupBy('supply_id')
            ->get()
            ->keyBy('supply_id');

        // Include historical quick purchases without inventing missing invoices or duplicating linked documents.
        $legacy = InventoryLog::query()->whereIn('supply_id', $ids)
            ->where('type', 'supplier_purchase')->whereNull('reversed_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('inventory_purchases')
                    ->whereColumn('inventory_purchases.inventory_log_id', 'inventory_logs.id');
            })
            ->selectRaw('supply_id, SUM(quantity) as entrada, SUM(quantity * unit_cost) as v_entrada, SUM(CASE WHEN unit_cost IS NULL THEN 1 ELSE 0 END) as missing_costs')
            ->groupBy('supply_id')->get();
        foreach ($legacy as $row) {
            $entry = $entradas->get($row->supply_id) ?? (object) ['entrada' => 0, 'v_entrada' => 0];
            $entry->entrada += (float) $row->entrada;
            $entry->v_entrada += (float) $row->v_entrada;
            $entry->missing_costs = (int) $row->missing_costs;
            $entradas->put($row->supply_id, $entry);
        }

        $salidas = InventoryLog::query()
            ->whereIn('supply_id', $ids)
            ->whereIn('type', self::SALE_EXIT_TYPES)
            ->whereNull('reversed_at')
            ->selectRaw('supply_id, COALESCE(SUM(ABS(quantity)), 0) as salida')
            ->groupBy('supply_id')
            ->get()
            ->keyBy('supply_id');

        $reservations = InventoryLog::query()->whereIn('supply_id', $ids)
            ->where('type', 'sale_reserved')->whereNull('reversed_at')
            ->selectRaw('supply_id, COALESCE(SUM(ABS(quantity)), 0) as reserved')
            ->groupBy('supply_id')->get()->keyBy('supply_id');

        foreach ($supplies as $supply) {
            $supply->commerce_metrics = $this->computeRow(
                $supply,
                $entradas->get($supply->id),
                $salidas->get($supply->id),
                (float) ($reservations->get($supply->id)?->reserved ?? 0),
            );
        }
    }

    public function computeRow(Supply $supply, ?object $entradaRow = null, ?object $salidaRow = null, float $reserved = 0): array
    {
        $entrada = (float) ($entradaRow->entrada ?? 0);
        $vEntrada = (float) ($entradaRow->v_entrada ?? 0);
        $salida = (float) ($salidaRow->salida ?? 0);

        $vSalida = $entrada > 0
            ? ($vEntrada / $entrada) * $salida
            : 0.0;

        $stock = (float) $supply->current_stock;
        $physical = $stock + $reserved;
        $vStock = $physical * (float) $supply->cost_per_unit;
        $precio = (float) ($supply->pvp ?? 0);
        $inventario = (float) ($supply->physical_count ?? 0);
        $vcu = (float) $supply->cost_per_unit;
        $diferencia = $physical - $inventario;

        return [
            'purchase_value_incomplete' => (int) ($entradaRow->missing_costs ?? 0) > 0,
            'entrada'     => $entrada,
            'v_entrada'   => $vEntrada,
            'salida'      => $salida,
            'v_salida'    => $vSalida,
            'stock'       => $stock,
            'reserved'    => $reserved,
            'physical'    => $physical,
            'v_stock'     => $vStock,
            'precio'      => $precio,
            'vcu'         => $vcu,
            'inventario'  => $inventario,
            'diferencia'  => $diferencia,
        ];
    }
}
