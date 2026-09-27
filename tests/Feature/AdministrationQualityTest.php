<?php

namespace Tests\Feature;

use App\Models\InventoryCsvUpload;
use App\Models\InventoryLog;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationQualityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_pin_attempts_are_limited_and_pin_is_not_flashed(): void
    {
        User::factory()->create(['role' => 'cook', 'pin_code' => '1234']);
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login.post'), ['pin_code' => '999999'])->assertRedirect();
        }
        $this->post(route('login.post'), ['pin_code' => '1234'])
            ->assertSessionHas('error', 'Demasiados intentos. Espera un minuto antes de volver a ingresar.');
        $this->assertGuest();
        $this->post(route('login.post'), ['pin_code' => 'not-a-pin'])->assertSessionHasErrors('pin_code');
        $this->assertNull(session()->getOldInput('pin_code'));
    }

    public function test_inactive_user_loses_existing_session(): void
    {
        $user = User::factory()->create(['role' => 'administrator', 'active' => false]);
        $this->actingAs($user)->get(route('admin.inventory.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_import_requires_review_and_repeated_confirmation_keeps_one_opening_balance(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'programmer']);
        $file = UploadedFile::fake()->createWithContent('supplies.csv', "CODIGO,ARTICULO,FAMILIA,P.V.P,STOCK,UNIDAD,COSTO\nA1,Arroz,granos,4,30,gram,2\n");
        $this->actingAs($user)->post(route('programmer.inventory-import.store'), [
            'csv_file' => $file, 'update_existing' => 1,
        ])->assertOk()->assertSee('Confirmar importación');
        $this->assertSame(0, Supply::count());
        $upload = InventoryCsvUpload::sole();
        $this->assertNull($upload->imported_at);
        $this->post(route('programmer.inventory-import.import-stored', $upload), ['confirm' => 1, 'update_existing' => 1])->assertRedirect();
        $this->assertNotNull($upload->fresh()->imported_at);
        $this->post(route('programmer.inventory-import.import-stored', $upload), ['confirm' => 1, 'update_existing' => 1])->assertRedirect();
        $this->assertSame(1, Supply::count());
        $this->assertSame(1, InventoryLog::count());
        $this->assertEquals(30, Supply::sole()->current_stock);
        $this->delete(route('programmer.inventory-import.destroy', $upload))->assertSessionHas('error');
        $this->assertNotNull($upload->fresh());
    }
}
