<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_daily_closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('fiscal_date')->unique();
            $table->foreignUuid('cashier_id')->constrained('users');
            $table->dateTime('closed_at');
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0);
            $table->integer('total_orders_count')->default(0);
            $table->integer('cancelled_orders_count')->default(0);
            $table->decimal('total_cancelled_amount', 12, 2)->default(0);
            $table->json('expenses')->nullable();
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('expected_cash_total', 12, 2)->default(0);
            $table->decimal('report_z_total_sales', 12, 2)->nullable();
            $table->decimal('difference_vs_report_z', 12, 2)->nullable();
            $table->string('pdf_local_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_daily_closures');
    }
};
