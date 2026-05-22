<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_daily_closures', function (Blueprint $table) {
            $table->json('cash_count_summary')->nullable()->after('expenses');
            $table->decimal('base_cash_total', 12, 2)->default(0)->after('cash_count_summary');
            $table->decimal('change_cash_total', 12, 2)->default(0)->after('base_cash_total');
            $table->decimal('sales_cash_total', 12, 2)->default(0)->after('change_cash_total');
            $table->decimal('declared_cash_total', 12, 2)->default(0)->after('sales_cash_total');
            $table->decimal('cash_difference', 12, 2)->default(0)->after('declared_cash_total');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_daily_closures', function (Blueprint $table) {
            $table->dropColumn([
                'cash_count_summary',
                'base_cash_total',
                'change_cash_total',
                'sales_cash_total',
                'declared_cash_total',
                'cash_difference',
            ]);
        });
    }
};
