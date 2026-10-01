<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', fn (Blueprint $table) => $table->timestamp('kitchen_sent_at')->nullable());
        // Existing dispatched orders are treated conservatively as sent; new additions remain unsent.
        DB::table('order_details')->whereIn('order_id', DB::table('orders')->whereNotNull('kitchen_sent_at')->select('id'))
            ->update(['kitchen_sent_at' => DB::raw('(SELECT orders.kitchen_sent_at FROM orders WHERE orders.id = order_details.order_id)')]);
    }

    public function down(): void
    {
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn('kitchen_sent_at'));
    }
};
