<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ModifierController;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModifierMigrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_modifier_migrations_can_be_rolled_back_and_applied_again(): void
    {
        $this->assertSame(15, Modifier::where('type', 'option')->count());
        $this->assertTrue(Schema::hasColumn('products', 'uses_product_modifiers'));

        $this->artisan('migrate:rollback', ['--path' => [
            'database/migrations/2026_06_03_000028_add_type_group_sort_to_modifiers_table.php',
            'database/migrations/2026_06_03_000029_create_modifier_assignment_tables.php',
            'database/migrations/2026_06_03_000030_seed_almuerzo_options.php',
            'database/migrations/2026_06_03_000031_add_uuid_defaults_to_modifier_assignment_tables.php',
        ], '--force' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('category_modifiers'));
        $this->assertFalse(Schema::hasTable('product_modifiers'));
        $this->assertFalse(Schema::hasColumn('modifiers', 'type'));
        $this->assertFalse(Schema::hasColumn('products', 'uses_product_modifiers'));

        ProductCategory::create(['name' => 'Almuerzos']);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $assignments = DB::table('category_modifiers')->get();
        $this->assertCount(15, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertTrue(Str::isUuid($assignment->id));
        }
    }

    public function test_category_and_product_assignments_receive_uuids_from_php(): void
    {
        $category = ProductCategory::create(['name' => 'Test category']);
        $product = Product::create(['name' => 'Test dish', 'price' => 10]);
        $modifier = Modifier::where('type', 'option')->firstOrFail();
        $controller = app(ModifierController::class);
        $request = Request::create('/', 'POST', ['modifiers' => [$modifier->id]]);

        $controller->syncCategory($request, $category);
        $controller->syncProduct($request, $product);

        foreach (['category_modifiers', 'product_modifiers'] as $table) {
            $assignment = DB::table($table)->sole();
            $this->assertTrue(Str::isUuid($assignment->id));
            $this->assertSame($modifier->id, $assignment->modifier_id);
        }
        $this->assertTrue($product->fresh()->uses_product_modifiers);
    }

    public function test_postgresql_defaults_generate_uuids_and_can_be_removed(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('UUID database defaults are PostgreSQL-specific.');
        }

        $category = ProductCategory::create(['name' => 'Test category']);
        $product = Product::create(['name' => 'Test dish', 'price' => 10]);
        $modifier = Modifier::where('type', 'option')->firstOrFail();

        foreach (['category_modifiers' => ['category_id' => $category->id],
            'product_modifiers' => ['product_id' => $product->id]] as $table => $parent) {
            $id = DB::table($table)->insertGetId($parent + ['modifier_id' => $modifier->id]);
            $this->assertTrue(Str::isUuid($id));
        }

        $this->artisan('migrate:rollback', [
            '--path' => ['database/migrations/2026_06_03_000031_add_uuid_defaults_to_modifier_assignment_tables.php'],
            '--force' => true,
        ])->assertExitCode(0);
        foreach (['category_modifiers', 'product_modifiers'] as $table) {
            $column = DB::selectOne(
                'SELECT column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, 'id']
            );
            $this->assertNotNull($column);
            $this->assertNull($column->column_default);
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }
}
