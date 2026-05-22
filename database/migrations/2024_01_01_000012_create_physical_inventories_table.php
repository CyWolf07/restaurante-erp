<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conteos Físicos (Inventario Ciego)
        Schema::create('physical_inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('users');
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->date('period_from')->nullable(); // Período analizado
            $table->date('period_to')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'recorded_at']);
        });

        // Detalles del conteo con análisis de incongruencias
        Schema::create('physical_inventory_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('physical_inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supply_id')->constrained();
            $table->decimal('theoretical_stock', 12, 4);  // Calculado desde inventory_logs
            $table->decimal('physical_stock', 12, 4);      // Ingresado manualmente
            $table->decimal('difference', 12, 4);          // physical - theoretical
            $table->decimal('volume_sold_period', 12, 4)->default(0); // Total vendido en el período
            $table->decimal('deviation_percentage', 8, 4)->default(0);
            $table->decimal('financial_impact', 12, 2)->default(0); // difference * cost_per_unit
            $table->decimal('cost_per_unit_snapshot', 12, 4)->default(0); // Snapshot del costo
            $table->enum('criticality', ['green', 'yellow', 'red'])->default('green');

            $table->index('physical_inventory_id');
            $table->index(['supply_id', 'criticality']);
            $table->index(['financial_impact']); // Para ordenar por mayor pérdida
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_inventory_details');
        Schema::dropIfExists('physical_inventories');
    }
};
