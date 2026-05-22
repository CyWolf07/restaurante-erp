<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('kitchen_sent_by')->nullable()->after('waiter_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('kitchen_sent_at')->nullable()->after('kitchen_sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_sent_by');
            $table->dropColumn('kitchen_sent_at');
        });
    }
};
