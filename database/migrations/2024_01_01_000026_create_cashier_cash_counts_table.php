<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_cash_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('fiscal_date')->unique();
            $table->foreignUuid('cashier_id')->constrained('users');
            $table->json('base_counts')->nullable();
            $table->json('change_counts')->nullable();
            $table->json('sales_counts')->nullable();
            $table->decimal('base_total', 12, 2)->default(0);
            $table->decimal('change_total', 12, 2)->default(0);
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->decimal('declared_cash_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_cash_counts');
    }
};
