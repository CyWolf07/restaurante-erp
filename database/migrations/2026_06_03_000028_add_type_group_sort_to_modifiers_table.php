<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            // 'addon'  = modificador con precio (comportamiento previo)
            // 'option' = opción de preparación sin costo para cocina
            $table->string('type', 20)->default('addon')->after('active');
            // Grupo visual para agrupar opciones en el modal (ej: "Sopa", "Sin", "Extras")
            $table->string('group', 80)->nullable()->after('type');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('group');
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn(['type', 'group', 'sort_order']);
        });
    }
};
