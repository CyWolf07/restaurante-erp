<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('number')->unique();
            $table->string('name')->nullable();
            $table->string('zone')->default('Salón');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->foreignUuid('kitchen_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->foreignUuid('receipt_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->unsignedSmallInteger('grid_row')->default(0);
            $table->unsignedSmallInteger('grid_col')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
            $table->index('zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
