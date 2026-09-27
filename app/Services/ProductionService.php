<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function create(array $data, User $user): ProductionOrder
    {
        Validator::make($data, [
            'request_key' => 'required|uuid', 'product_id' => 'required|uuid|exists:products,id',
            'output_supply_id' => 'required|uuid|exists:supplies,id',
            'planned_quantity' => 'required|numeric|min:0.0001|max:999999',
        ])->validate();

        return app(PosOperationService::class)->run(function () use ($data, $user) {
            $existing = ProductionOrder::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->created_by !== $user->id || $existing->product_id !== $data['product_id']
                    || $existing->output_supply_id !== $data['output_supply_id']
                    || (float) $existing->planned_quantity !== (float) $data['planned_quantity']) {
                    throw ValidationException::withMessages(['production' => 'La solicitud ya existe con otros datos.']);
                }

                return $existing;
            }
            $product = Product::whereKey($data['product_id'])->where('active', true)->lockForUpdate()->firstOrFail();
            $output = Supply::whereKey($data['output_supply_id'])->where('active', true)->firstOrFail();
            $product->load('recipes.supply');
            if ($product->recipes->isEmpty()) {
                throw ValidationException::withMessages(['production' => 'La ficha seleccionada no tiene ingredientes.']);
            }
            $ingredients = [];
            foreach ($product->recipes as $recipe) {
                if (! $recipe->supply?->active || $recipe->supply_id === $output->id || $recipe->quantity_required <= 0) {
                    throw ValidationException::withMessages(['production' => 'Revisa los ingredientes: deben estar activos y ser distintos del elaborado de salida.']);
                }
                $required = round((float) $recipe->quantity_required * (float) $data['planned_quantity'], 4);
                if ($required < 0.0001 || $required > 99999999.9999) {
                    throw ValidationException::withMessages(['production' => 'La cantidad calculada de un ingrediente está fuera de la precisión o rango permitido.']);
                }
                $ingredients[] = ['supply_id' => $recipe->supply_id, 'name' => $recipe->supply->name,
                    'unit_type' => $recipe->supply->unit_type, 'per_unit' => $recipe->quantity_required,
                    'quantity' => $required];
            }

            return ProductionOrder::create([
                'request_key' => $data['request_key'], 'product_id' => $product->id,
                'output_supply_id' => $output->id, 'planned_quantity' => $data['planned_quantity'],
                'status' => 'draft', 'created_by' => $user->id,
                'recipe_snapshot' => ['name' => $product->name, 'version' => $product->recipe_version,
                    'instructions' => $product->recipe_instructions, 'output_name' => $output->name,
                    'output_unit' => $output->unit_type, 'ingredients' => $ingredients],
            ]);
        }, false);
    }

    public function complete(ProductionOrder $production, float $actualQuantity, User $user): ProductionOrder
    {
        Validator::make(['actual_quantity' => $actualQuantity], ['actual_quantity' => 'required|numeric|min:0.0001|max:999999'])->validate();

        return app(PosOperationService::class)->run(function () use ($production, $actualQuantity, $user) {
            $production = ProductionOrder::whereKey($production->id)->lockForUpdate()->firstOrFail();
            if ($production->status === 'completed') {
                if ((float) $production->actual_quantity !== $actualQuantity) {
                    throw ValidationException::withMessages(['production' => 'El lote ya fue cerrado con otra cantidad.']);
                }

                return $production;
            }
            if ($production->status !== 'draft') {
                throw ValidationException::withMessages(['production' => 'La orden está anulada.']);
            }
            $snapshot = $production->recipe_snapshot;
            $ingredients = collect($snapshot['ingredients'])->groupBy('supply_id');
            $ids = $ingredients->keys()->push($production->output_supply_id)->unique()->sort()->values();
            $supplies = Supply::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $totalCost = 0;
            foreach ($ingredients as $id => $rows) {
                $supply = $supplies->get($id);
                $quantity = round((float) $rows->sum('quantity'), 4);
                if (! $supply?->active || $supply->unit_type !== $rows->first()['unit_type']
                    || $quantity <= 0 || (float) $supply->current_stock < $quantity) {
                    throw ValidationException::withMessages(['production' => 'Ingrediente sin disponibilidad o con unidad modificada: '.$rows->first()['name']]);
                }
                $totalCost += $quantity * (float) $supply->cost_per_unit;
                $this->move($production, $supply, -$quantity, (float) $supply->cost_per_unit, 'production_consumption', $user);
            }
            $output = $supplies->get($production->output_supply_id);
            if (! $output?->active || $output->unit_type !== $snapshot['output_unit']) {
                throw ValidationException::withMessages(['production' => 'El elaborado de salida está inactivo o su unidad cambió.']);
            }
            $unitCost = round($totalCost / $actualQuantity, 4);
            if ($unitCost > 99999999.9999 || (float) $output->current_stock + $actualQuantity > 99999999.9999) {
                throw ValidationException::withMessages(['production' => 'El costo unitario o saldo resultante supera el rango permitido.']);
            }
            $this->move($production, $output, $actualQuantity, $unitCost, 'production_output', $user);
            // Preserve the current last-acquisition valuation policy until the business chooses another method.
            $output->update(['cost_per_unit' => $unitCost]);
            $production->update(['status' => 'completed', 'actual_quantity' => $actualQuantity,
                'total_cost' => round($totalCost, 4), 'completed_by' => $user->id, 'completed_at' => now()]);

            return $production;
        }, false);
    }

    public function cancel(ProductionOrder $production, string $reason, User $user): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:500'])->validate();
        app(PosOperationService::class)->run(function () use ($production, $reason, $user) {
            $production = ProductionOrder::whereKey($production->id)->lockForUpdate()->firstOrFail();
            if ($production->status === 'cancelled') {
                return;
            }
            if ($production->status !== 'draft') {
                throw ValidationException::withMessages(['production' => 'Un lote cerrado no puede anularse como borrador.']);
            }
            $production->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $user->id]);
        }, false);
    }

    private function move(ProductionOrder $production, Supply $supply, float $quantity, float $unitCost, string $type, User $user): void
    {
        $stock = round((float) $supply->current_stock + $quantity, 4);
        $supply->update(['current_stock' => $stock]);
        InventoryLog::create(['supply_id' => $supply->id, 'production_order_id' => $production->id,
            'type' => $type, 'quantity' => $quantity, 'stock_after' => $stock, 'unit_cost' => $unitCost,
            'user_id' => $user->id, 'description' => 'Producción '.$production->id, 'created_at' => now()]);
    }
}
