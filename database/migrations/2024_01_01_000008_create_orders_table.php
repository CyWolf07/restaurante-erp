<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('table_number');
            $table->foreignUuid('waiter_id')->constrained('users');
            $table->foreignUuid('cashier_id')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'in_kitchen', 'ready', 'paid', 'cancelled'])
                  ->default('pending');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('locked_at')->nullable(); // Inmutabilidad post-cierre Z
            $table->timestamps();

            $table->index('status');
            $table->index('table_number');
            $table->index(['status', 'created_at']); // Para cierre del día
            $table->index('waiter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
