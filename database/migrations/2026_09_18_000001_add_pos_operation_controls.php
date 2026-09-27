<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_operation_locks', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('used_at')->nullable();
        });
        DB::table('pos_operation_locks')->insert(['name' => 'operations']);

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable();
        });

        // The original SQLite CHECK still only accepts the four initial types.
        if (DB::connection()->getDriverName() === 'sqlite' && ! Schema::hasTable('production_orders')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->enum('type', [
                    'sale_consumption', 'manual_waste', 'supplier_purchase', 'programmer_adjustment',
                    'sale_reserved', 'sale_confirmed', 'purchase_entry', 'sale_reversal',
                ])->change();
            });
        }

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained();
            $table->string('action', 40);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('reversed_at');
        });
        Schema::dropIfExists('pos_operation_locks');
        // Preserve the expanded inventory types; existing rows may use them.
    }
};
