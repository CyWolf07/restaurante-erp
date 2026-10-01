<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->boolean('require_hold_reason')->default(true);
            $table->timestamps();
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('status', 30)->default('pending_review')->index();
            $table->string('document_type', 40)->default('electronic_invoice');
            $table->string('payment_method', 30)->default('cash');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->json('buyer_data')->nullable();
            $table->json('document_snapshot');
            $table->text('notes')->nullable();
            $table->text('hold_reason')->nullable();
            $table->foreignUuid('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->string('provider', 100)->nullable();
            $table->string('external_number', 100)->nullable()->unique();
            $table->string('fiscal_identifier', 200)->nullable()->unique();
            $table->json('provider_response')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('daily_reports_z', function (Blueprint $table) {
            $table->unsignedInteger('fiscal_pending_count')->default(0);
            $table->decimal('fiscal_pending_total', 12, 2)->default(0);
        });

        Schema::table('cashier_daily_closures', function (Blueprint $table) {
            $table->unsignedInteger('fiscal_pending_count')->default(0);
            $table->decimal('fiscal_pending_total', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cashier_daily_closures', function (Blueprint $table) {
            $table->dropColumn(['fiscal_pending_count', 'fiscal_pending_total']);
        });

        Schema::table('daily_reports_z', function (Blueprint $table) {
            $table->dropColumn(['fiscal_pending_count', 'fiscal_pending_total']);
        });

        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('fiscal_settings');
    }
};
