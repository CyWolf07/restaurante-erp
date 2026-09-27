<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\InventoryPurchase;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryEngine;
use App\Services\InventoryPurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CredentialsAndPurchasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_pin_is_hashed_hidden_and_usable_for_login(): void
    {
        $user = User::factory()->create(['role' => 'cook', 'pin_code' => '0987']);
        $this->assertNull($user->fresh()->getRawOriginal('pin_code'));
        $this->assertTrue(Hash::check('0987', $user->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $user->toArray());
        $this->assertArrayNotHasKey('pin_lookup', $user->toArray());
        $this->post(route('login.post'), ['pin_code' => '0987'])->assertRedirect(route('cook.recipes'));
        $this->assertAuthenticatedAs($user);
        $user->update(['pin_code' => '0988']);
        $this->assertNull(User::findByPin('0987'));
        $this->assertSame($user->id, User::findByPin('0988')->id);
        $this->expectException(ValidationException::class);
        User::factory()->create(['pin_code' => '0988']);
    }

    public function test_existing_pin_migrates_without_changing_the_login_code(): void
    {
        $migration = require database_path('migrations/2026_09_21_000003_secure_staff_pins.php');
        $migration->down();
        $id = (string) Str::uuid();
        DB::table('users')->insert(['id' => $id, 'name' => 'Legacy', 'role' => 'cook', 'pin_code' => '0012', 'active' => true]);
        $migration->up();
        $this->assertNull(DB::table('users')->where('id', $id)->value('pin_code'));
        $this->assertSame($id, User::findByPin('0012')->id);
        try {
            $migration->down();
            $this->fail('Rollback must not discard protected credentials.');
        } catch (\RuntimeException) {
            $this->assertSame($id, User::findByPin('0012')->id);
        }
    }

    public function test_repeated_purchase_has_one_document_and_one_stock_entry(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);
        $supply = Supply::create(['code' => 'TEST', 'name' => 'Arroz', 'unit_type' => 'gram', 'current_stock' => 10]);
        $data = ['operation_key' => (string) Str::uuid(), 'code' => 'TEST', 'supplier' => 'Proveedor',
            'purchase_date' => today()->toDateString(), 'adjustment_type' => 'compra', 'quantity' => 20,
            'unit_value' => 2, 'invoice_number' => 'ABC', 'point' => 'bodega'];
        $service = app(InventoryPurchaseService::class);
        $first = $service->register($data, $user);
        $this->assertSame($first->id, $service->register($data, $user)->id);
        $this->assertEquals(30, $supply->fresh()->current_stock);
        $this->assertSame(1, InventoryPurchase::count());
        $this->assertSame(1, InventoryLog::count());
        $this->expectException(ValidationException::class);
        $service->register(array_replace($data, ['quantity' => 21]), $user);
    }

    public function test_repeated_simple_purchase_and_waste_are_idempotent(): void
    {
        $user = User::factory()->create();
        $supply = Supply::create(['name' => 'Stock', 'unit_type' => 'unit', 'current_stock' => 10]);
        $engine = app(InventoryEngine::class);
        $purchaseKey = (string) Str::uuid();
        $wasteKey = (string) Str::uuid();
        for ($i = 0; $i < 2; $i++) {
            $engine->registerPurchase($supply, 3, $user->id, 'Compra', $purchaseKey);
            $engine->registerWaste($supply, 1, $user->id, 'Merma', $wasteKey);
        }
        $this->assertEquals(12, $supply->fresh()->current_stock);
        $this->assertSame(2, InventoryLog::count());
    }

    public function test_staff_audit_does_not_record_credentials_and_cashier_cannot_read_it(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'pin_code' => '1000']);
        $this->actingAs($admin)->post(route('admin.staff.store'), ['name' => 'Chef', 'role' => 'cook', 'auth_type' => 'pin', 'pin_code' => '6743'])
            ->assertSessionHasNoErrors();
        $event = DB::table('audit_events')->sole();
        $this->assertStringNotContainsString('6743', $event->data);
        $this->assertStringNotContainsString('pin_hash', $event->data);
        $this->get(route('admin.audit'))->assertOk()->assertSee('staff_created');
        $cashier = User::factory()->create(['role' => 'cashier', 'pin_code' => '1002']);
        $this->actingAs($cashier)->get(route('admin.audit'))->assertForbidden();
    }
}
