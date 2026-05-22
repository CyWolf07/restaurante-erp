<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supply_id')->constrained('supplies');
            $table->string('code', 50);
            $table->string('supplier');
            $table->date('purchase_date');
            $table->enum('adjustment_type', ['compra', 'bonificacion', 'inventario', 'reposicion']);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_value', 12, 2);
            $table->decimal('total_value', 12, 2);
            $table->string('invoice_number', 100)->nullable();
            $table->enum('point', ['el_muelle', 'bocagrande', 'oficinas', 'bodega']);
            $table->foreignUuid('user_id')->constrained('users');
            $table->unsignedBigInteger('inventory_log_id')->nullable();
            $table->timestamps();

            $table->index(['purchase_date', 'point']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchases');
    }
};
