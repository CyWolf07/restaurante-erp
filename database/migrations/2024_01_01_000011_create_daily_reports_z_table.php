<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cierre Diario Fiscal — Inmutable
        Schema::create('daily_reports_z', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('fiscal_date')->unique(); // Un cierre por día
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0); // Neto sin impuesto
            $table->integer('total_orders_count')->default(0);
            $table->integer('cancelled_orders_count')->default(0);
            $table->decimal('total_cancelled_amount', 12, 2)->default(0);
            $table->foreignUuid('cashier_id')->constrained('users');
            $table->string('pdf_local_path', 500)->nullable();
            $table->string('database_backup_path', 500)->nullable();
            $table->jsonb('summary_data')->nullable(); // Resumen detallado de ventas por categoría
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports_z');
    }
};
