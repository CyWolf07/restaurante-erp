<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\PhysicalInventory;
use App\Models\PhysicalInventoryDetail;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function analyzePhysicalInventory(
        User $admin, array $physicalCounts,
        ?Carbon $periodFrom = null, ?Carbon $periodTo = null, ?string $notes = null
    ): PhysicalInventory {
        $periodFrom = $periodFrom ?? Carbon::now()->subDays(30);
        $periodTo   = $periodTo ?? Carbon::now();

        \Illuminate\Support\Facades\Validator::make(['counts' => $physicalCounts], [
            'counts' => 'required|array|min:1',
            'counts.*' => 'required|numeric|min:0|max:99999999.9999',
        ])->validate();

        return app(PosOperationService::class)->run(function () use ($admin, $physicalCounts, $periodFrom, $periodTo, $notes) {
            $supplies = Supply::whereIn('id', array_keys($physicalCounts))->where('active', true)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($supplies->count() !== count($physicalCounts)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['counts' => 'Hay insumos inexistentes o desactivados. Actualiza el formulario.']);
            }
            $inventory = PhysicalInventory::create([
                'admin_id' => $admin->id, 'recorded_at' => now(),
                'notes' => $notes, 'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
            ]);

            foreach ($physicalCounts as $supplyId => $physicalStock) {
                $supply = $supplies->get($supplyId);
                $this->analyzeSupply($inventory, $supply, (float)$physicalStock, $periodFrom, $periodTo);
            }
            return $inventory->load('details.supply');
        }, false);
    }

    private function analyzeSupply(PhysicalInventory $inv, Supply $supply, float $physicalStock, Carbon $from, Carbon $to): PhysicalInventoryDetail
    {
        $reserved = abs((float) $supply->inventoryLogs()->where('type', 'sale_reserved')->whereNull('reversed_at')->sum('quantity'));
        $theoreticalStock = (float) $supply->current_stock + $reserved;
        $difference = $physicalStock - $theoreticalStock;
        $volumeSold = (float) abs(InventoryLog::where('supply_id', $supply->id)
            ->whereIn('type', ['sale_confirmed', 'sale_consumption'])->whereBetween('created_at', [$from, $to])->sum('quantity'));
        $deviationPct = $volumeSold > 0 ? (abs($difference) / $volumeSold) * 100 : ($difference != 0 ? 100 : 0);
        $criticality = $deviationPct < 1.5 ? 'green' : ($deviationPct < 5.0 ? 'yellow' : 'red');
        $financialImpact = abs($difference) * $supply->cost_per_unit;

        return PhysicalInventoryDetail::create([
            'physical_inventory_id' => $inv->id, 'supply_id' => $supply->id,
            'theoretical_stock' => $theoreticalStock, 'physical_stock' => $physicalStock,
            'difference' => $difference, 'volume_sold_period' => $volumeSold,
            'deviation_percentage' => round($deviationPct, 4), 'financial_impact' => round($financialImpact, 2),
            'cost_per_unit_snapshot' => $supply->cost_per_unit, 'criticality' => $criticality,
        ]);
    }

    public function getInconsistencyDashboard(PhysicalInventory $inventory): Collection
    {
        return $inventory->details()->with('supply')->orderByDesc('financial_impact')->get()
            ->map(fn($d) => [
                'supply_name' => $d->supply->name, 'unit_type' => $d->supply->unit_label,
                'theoretical_stock' => $d->theoretical_stock, 'physical_stock' => $d->physical_stock,
                'difference' => $d->difference, 'deviation_percentage' => $d->deviation_percentage,
                'financial_impact' => $d->financial_impact, 'criticality' => $d->criticality,
                'criticality_label' => $d->criticality_label, 'criticality_color' => $d->criticality_color,
            ]);
    }

    public function getConsumptionComparison(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? Carbon::now()->subDays(30);
        $to   = $to ?? Carbon::now();
        $supplies = Supply::where('active', true)->get();
        $totals = InventoryLog::query()->whereBetween('created_at', [$from, $to])
            ->whereNull('reversed_at')
            ->whereIn('type', ['sale_confirmed', 'sale_consumption', 'manual_waste'])
            ->selectRaw("supply_id, SUM(CASE WHEN type IN ('sale_confirmed', 'sale_consumption') THEN quantity ELSE 0 END) as theoretical, SUM(quantity) as total_exits")
            ->groupBy('supply_id')->get()->keyBy('supply_id');
        $labels = $theoretical = $real = $colors = [];

        foreach ($supplies as $supply) {
            $theo = abs((float) ($totals->get($supply->id)?->theoretical ?? 0));
            if ($theo == 0) continue;
            $totalExits = abs((float) ($totals->get($supply->id)?->total_exits ?? 0));
            $labels[] = $supply->name;
            $theoretical[] = round($theo, 2);
            $real[] = round($totalExits, 2);
            $dev = $totalExits > 0 ? abs($totalExits - $theo) / $totalExits * 100 : 0;
            $colors[] = $dev >= 5 ? '#ef4444' : ($dev >= 1.5 ? '#f59e0b' : '#22c55e');
        }
        return compact('labels', 'theoretical', 'real', 'colors');
    }
}
