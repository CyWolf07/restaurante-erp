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
            $table->foreignUuid('restaurant_table_id')->nullable()->after('table_number')
                ->constrained('restaurant_tables')->nullOnDelete();
        });

        if (Schema::hasTable('restaurant_tables')) {
            $tables = DB::table('restaurant_tables')->pluck('id', 'number');
            foreach ($tables as $number => $id) {
                DB::table('orders')->where('table_number', $number)->update(['restaurant_table_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_table_id');
        });
    }
};
