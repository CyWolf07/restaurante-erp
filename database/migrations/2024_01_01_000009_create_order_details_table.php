<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); // Snapshot del precio al momento de la venta
            $table->decimal('subtotal', 12, 2);
            $table->text('comments')->nullable(); // Instrucciones especiales de cocina
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });

        Schema::create('order_detail_modifiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('order_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->constrained();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->index('order_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_detail_modifiers');
        Schema::dropIfExists('order_details');
    }
};
