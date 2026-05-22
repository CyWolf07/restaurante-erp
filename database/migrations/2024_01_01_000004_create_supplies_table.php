<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('unit_type', ['gram', 'milliliter', 'unit']);
            $table->decimal('current_stock', 12, 4)->default(0);
            $table->decimal('min_stock', 12, 4)->default(0);
            $table->decimal('cost_per_unit', 12, 4)->default(0); // costo por gramo/ml/unidad
            $table->string('supplier')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index(['current_stock', 'min_stock']); // Para alertas de stock bajo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
