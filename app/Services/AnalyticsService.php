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

        return DB::transaction(function () use ($admin, $physicalCounts, $periodFrom, $periodTo, $notes) {
            $inventory = PhysicalInventory::create([
                'admin_id' => $admin->id, 'recorded_at' => now(),
                'notes' => $notes, 'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
            ]);

            foreach ($physicalCounts as $supplyId => $physicalStock) {
                $supply = Supply::find($supplyId);
                if (!$supply) continue;
                $this->analyzeSupply($inventory, $supply, (float)$physicalStock, $periodFrom, $periodTo);
            }
            return $inventory->load('details.supply');
        });
    }

    private function analyzeSupply(PhysicalInventory $inv, Supply $supply, float $physicalStock, Carbon $from, Carbon $to): PhysicalInventoryDetail
    {
        $theoreticalStock = (float) $supply->current_stock;
        $difference = $physicalStock - $theoreticalStock;
        $volumeSold = (float) abs(InventoryLog::where('supply_id', $supply->id)
            ->where('type', 'sale_consumption')->whereBetween('created_at', [$from, $to])->sum('quantity'));
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
        $labels = $theoretical = $real = $colors = [];

        foreach ($supplies as $supply) {
            $theo = (float) abs(InventoryLog::where('supply_id', $supply->id)
                ->where('type', 'sale_consumption')->whereBetween('created_at', [$from, $to])->sum('quantity'));
            if ($theo == 0) continue;
            $totalExits = (float) abs(InventoryLog::where('supply_id', $supply->id)
                ->whereIn('type', ['sale_consumption', 'manual_waste'])->whereBetween('created_at', [$from, $to])->sum('quantity'));
            $labels[] = $supply->name;
            $theoretical[] = round($theo, 2);
            $real[] = round($totalExits, 2);
            $dev = $totalExits > 0 ? abs($totalExits - $theo) / $totalExits * 100 : 0;
            $colors[] = $dev >= 5 ? '#ef4444' : ($dev >= 1.5 ? '#f59e0b' : '#22c55e');
        }
        return compact('labels', 'theoretical', 'real', 'colors');
    }
}
