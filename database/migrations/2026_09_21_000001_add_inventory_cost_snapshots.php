<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_cost_captured_at')->nullable();
        });
        Schema::table('inventory_logs', function (Blueprint $table) {
            // Unknown historical costs stay null; never reconstruct them from today's prices.
            $table->decimal('unit_cost', 16, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('inventory_cost_captured_at');
        });
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
