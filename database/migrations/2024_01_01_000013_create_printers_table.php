<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('purpose', ['kitchen', 'receipt', 'bar'])->default('kitchen');
            $table->enum('connection_type', ['network', 'windows'])->default('network');
            $table->string('address'); // IP o nombre de impresora Windows
            $table->unsignedSmallInteger('port')->default(9100);
            $table->unsignedSmallInteger('paper_width')->default(80); // 58 o 80 mm
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'active']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
