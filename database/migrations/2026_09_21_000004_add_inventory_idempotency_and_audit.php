<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->uuid('operation_key')->nullable()->unique();
            $table->string('operation_signature', 64)->nullable();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('entity_type', 50);
            $table->string('entity_id', 64);
            $table->string('action', 50);
            $table->json('data');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
        });
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn(['operation_key', 'operation_signature']);
        });
    }
};
