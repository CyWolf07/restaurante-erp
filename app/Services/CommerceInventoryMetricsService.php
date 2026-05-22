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
 * STOCK                ← ENTRADA − SALIDA
 * V/STOCK              ← V/ENTRADA − V/SALIDA
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

        $salidas = InventoryLog::query()
            ->whereIn('supply_id', $ids)
            ->whereIn('type', self::SALE_EXIT_TYPES)
            ->selectRaw('supply_id, COALESCE(SUM(ABS(quantity)), 0) as salida')
            ->groupBy('supply_id')
            ->get()
            ->keyBy('supply_id');

        foreach ($supplies as $supply) {
            $supply->commerce_metrics = $this->computeRow(
                $supply,
                $entradas->get($supply->id),
                $salidas->get($supply->id),
            );
        }
    }

    public function computeRow(Supply $supply, ?object $entradaRow = null, ?object $salidaRow = null): array
    {
        $entrada = (float) ($entradaRow->entrada ?? 0);
        $vEntrada = (float) ($entradaRow->v_entrada ?? 0);
        $salida = (float) ($salidaRow->salida ?? 0);

        $vSalida = $entrada > 0
            ? ($vEntrada / $entrada) * $salida
            : 0.0;

        $stock = $entrada - $salida;
        $vStock = $vEntrada - $vSalida;
        $precio = (float) ($supply->pvp ?? 0);
        $inventario = (float) ($supply->physical_count ?? 0);
        $vcu = $stock != 0.0 ? $vStock / $stock : 0.0;
        $diferencia = $stock - $inventario;

        return [
            'entrada'     => $entrada,
            'v_entrada'   => $vEntrada,
            'salida'      => $salida,
            'v_salida'    => $vSalida,
            'stock'       => $stock,
            'v_stock'     => $vStock,
            'precio'      => $precio,
            'vcu'         => $vcu,
            'inventario'  => $inventario,
            'diferencia'  => $diferencia,
        ];
    }
}
