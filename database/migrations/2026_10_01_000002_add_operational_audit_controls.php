<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('payment_breakdown')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('tax_type', 20)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('tax_type', 20)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
        });
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->string('validation_evidence_path', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', fn (Blueprint $table) => $table->dropColumn('validation_evidence_path'));
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn(['tax_type', 'tax_rate']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['tax_type', 'tax_rate']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('payment_breakdown'));
    }
};
