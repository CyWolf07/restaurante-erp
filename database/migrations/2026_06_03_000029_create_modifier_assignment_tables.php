<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->constrained('modifiers')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['category_id', 'modifier_id']);
            $table->timestamps();
        });

        Schema::create('product_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->constrained('modifiers')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['product_id', 'modifier_id']);
            $table->timestamps();
        });

        // Flag en productos: si tiene overrides propios ignora los de categoría
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('uses_product_modifiers')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('uses_product_modifiers');
        });
        Schema::dropIfExists('product_modifiers');
        Schema::dropIfExists('category_modifiers');
    }
};
