<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('id');
            $table->text('description')->nullable()->after('name');
            $table->enum('point', ['el_muelle', 'bocagrande', 'oficinas', 'bodega'])->default('el_muelle')->after('supplier');
            $table->string('location', 255)->nullable()->after('point');
            $table->unsignedTinyInteger('department_number')->nullable()->after('location');
            $table->enum('family', [
                'proteina', 'granos_abarrotes', 'frutas', 'verduras',
                'lacteos_huevos', 'pulpas', 'hierbas', 'helados', 'bebidas_embotelladas',
            ])->nullable()->after('department_number');
            $table->decimal('pvp', 12, 2)->default(0)->after('cost_per_unit');

            $table->index('code');
            $table->index(['point', 'family']);
        });
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->dropIndex(['point', 'family']);
            $table->dropIndex(['code']);
            $table->dropColumn([
                'code', 'description', 'point', 'location',
                'department_number', 'family', 'pvp',
            ]);
        });
    }
};
