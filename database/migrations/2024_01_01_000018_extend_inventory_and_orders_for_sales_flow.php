<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_reserved_at')->nullable()->after('locked_at');
            $table->timestamp('inventory_confirmed_at')->nullable()->after('inventory_reserved_at');
            $table->boolean('preticket_printed')->default(false)->after('inventory_confirmed_at');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignUuid('order_id')->nullable()->after('order_detail_id')
                ->constrained('orders')->nullOnDelete();
        });

        // Laravel enum en PostgreSQL usa CHECK, no un tipo ENUM nombrado
        $types = [
            'sale_consumption', 'manual_waste', 'supplier_purchase', 'programmer_adjustment',
            'sale_reserved', 'sale_confirmed', 'purchase_entry', 'sale_reversal',
        ];
        $list = "'" . implode("','", $types) . "'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_logs DROP CONSTRAINT IF EXISTS inventory_logs_type_check');
            DB::statement("ALTER TABLE inventory_logs ADD CONSTRAINT inventory_logs_type_check CHECK (type::text IN ({$list}))");
        }
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['inventory_reserved_at', 'inventory_confirmed_at', 'preticket_printed']);
        });
    }
};
