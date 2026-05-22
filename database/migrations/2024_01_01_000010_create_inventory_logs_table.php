<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro Mayor de Inventario — Append Only, nunca se modifica
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->bigIncrements('id'); // BigInt — millones de registros
            $table->foreignUuid('supply_id')->constrained();
            $table->enum('type', [
                'sale_consumption',     // Deducción automática por venta
                'manual_waste',         // Merma manual registrada
                'supplier_purchase',    // Compra a proveedor (entrada)
                'programmer_adjustment' // Ajuste de parche por integridad
            ]);
            $table->decimal('quantity', 12, 4); // Positivo=entrada, Negativo=salida
            $table->decimal('stock_after', 12, 4); // Snapshot del stock DESPUÉS de la operación
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('order_detail_id')->nullable()->constrained('order_details')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent(); // Sin updated_at — append only

            $table->index(['supply_id', 'created_at']);
            $table->index('type');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
