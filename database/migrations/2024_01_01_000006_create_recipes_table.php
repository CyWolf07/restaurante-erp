<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla pivote / Ficha Técnica (Bill of Materials)
        Schema::create('recipes', function (Blueprint $table) {
            $table->bigIncrements('id'); // BigInt — tabla de alta frecuencia de lectura
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supply_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_required', 12, 4); // gramos/ml/unidades exactos
            $table->timestamps();

            $table->unique(['product_id', 'supply_id']); // Un insumo por plato
            $table->index('product_id');
            $table->index('supply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
