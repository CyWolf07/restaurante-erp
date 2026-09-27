<?php

use App\Support\PinCredential;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pins = DB::table('users')->whereNotNull('pin_code')->where('pin_code', '<>', '')->pluck('pin_code', 'id');
        if ($pins->unique()->count() !== $pins->count()) {
            throw new RuntimeException('Existen PIN compartidos. Asigna PIN distintos al personal antes de actualizar.');
        }
        // Validate the key before modifying the schema or clearing any credential.
        PinCredential::fingerprint('migration-check');
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();
            $table->string('pin_lookup', 64)->nullable()->unique();
        });
        foreach ($pins as $id => $pin) {
            DB::table('users')->where('id', $id)->update([
                'pin_hash' => Hash::make($pin), 'pin_lookup' => PinCredential::fingerprint($pin), 'pin_code' => null,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('pin_hash')->exists()) {
            throw new RuntimeException('Los PIN protegidos no se pueden convertir a texto. Para volver a la versión anterior restaura el respaldo previo completo.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pin_lookup']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_lookup']);
        });
    }
};
