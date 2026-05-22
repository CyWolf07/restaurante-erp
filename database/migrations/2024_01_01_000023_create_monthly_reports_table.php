<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_ingredient_cost', 14, 2)->default(0);
            $table->decimal('gross_profit', 14, 2)->default(0);
            $table->integer('total_orders_count')->default(0);
            $table->integer('total_products_sold')->default(0);
            $table->foreignUuid('closed_by')->constrained('users');
            $table->string('pdf_local_path', 500)->nullable();
            $table->longText('compressed_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
