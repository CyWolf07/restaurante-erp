<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('recipe_version')->default(1);
        });
        Schema::create('production_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_key')->unique();
            $table->foreignUuid('product_id')->index()->constrained();
            $table->foreignUuid('output_supply_id')->index()->constrained('supplies');
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('planned_quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();
            $table->json('recipe_snapshot');
            $table->foreignUuid('created_by')->index()->constrained('users');
            $table->foreignUuid('completed_by')->nullable()->index()->constrained('users');
            $table->foreignUuid('cancelled_by')->nullable()->index()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignUuid('production_order_id')->nullable()->index()->constrained('production_orders');
        });
        $types = ['sale_consumption', 'manual_waste', 'supplier_purchase', 'programmer_adjustment',
            'sale_reserved', 'sale_confirmed', 'purchase_entry', 'sale_reversal',
            'production_consumption', 'production_output'];
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_logs DROP CONSTRAINT IF EXISTS inventory_logs_type_check');
            $list = "'".implode("','", $types)."'";
            DB::statement("ALTER TABLE inventory_logs ADD CONSTRAINT inventory_logs_type_check CHECK (type IN ({$list}))");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('inventory_logs', function (Blueprint $table) use ($types) {
                $table->enum('type', $types)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex(['production_order_id']);
        });
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_order_id');
        });
        Schema::dropIfExists('production_orders');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('recipe_version');
        });
        // Keep accepted movement types: historical production movements may remain.
    }
};
