<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite uses the UUIDs supplied by PHP when assignments are created.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('ALTER TABLE category_modifiers ALTER COLUMN id SET DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE product_modifiers ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE category_modifiers ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE product_modifiers ALTER COLUMN id DROP DEFAULT');
    }
};
