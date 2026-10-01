<?php

namespace Tests\Feature;

use App\Models\CashierCashCount;
use App\Models\CashierDailyClosure;
use App\Models\DailyReportZ;
use App\Models\FiscalDocument;
use App\Models\FiscalSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\CashierDailyClosureService;
use App\Services\ReportZService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalDocumentWorkflowTest extends TestCase
{
    private User $cashier;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Bus::fake();
        config(['app.tax_rate' => 0]);

        $this->cashier = User::factory()->create(['role' => 'cashier']);
        $waiter = User::factory()->create(['role' => 'waiter']);
        $table = RestaurantTable::create(['number' => 1, 'active' => true]);
        $product = Product::create(['name' => 'Almuerzo del día', 'price' => 25000, 'active' => true]);
        $this->order = Order::create([
            'table_number' => 1,
            'restaurant_table_id' => $table->id,
            'waiter_id' => $waiter->id,
            'status' => 'ready',
        ]);
        OrderDetail::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'discount' => 5000,
            'subtotal' => 45000,
        ]);
        $this->order->recalculateTotals();
    }

    public function test_payment_creates_an_unsent_autofilled_fiscal_draft(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000,
            'payment_method' => 'card',
        ])->assertRedirect(route('cashier.pos'));

        $document = FiscalDocument::sole();
        $this->assertSame('pending_review', $document->status);
        $this->assertSame('card', $document->payment_method);
        $this->assertSame(45000.0, (float) $document->total);
        $this->assertSame('Almuerzo del día', data_get($document->document_snapshot, 'items.0.description'));
        $this->assertSame('5000.00', data_get($document->document_snapshot, 'items.0.discount'));
        $this->assertNull($document->external_number);
        $this->assertNull($document->submitted_at);
    }

    public function test_hold_keeps_document_active_and_counted_until_real_evidence_is_registered(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000,
            'payment_method' => 'cash',
        ]);
        $document = FiscalDocument::sole();

        $this->post(route('cashier.fiscal-documents.hold', $document), [
            'reason' => 'Documento del comprador incorrecto',
        ])->assertRedirect(route('cashier.fiscal-documents.index'));

        $this->assertSame('held_for_correction', $document->fresh()->status);
        $this->get(route('cashier.fiscal-documents.index'))->assertOk()
            ->assertSee('45.000')->assertSee('Retenida para corregir');

        Storage::fake('local');
        $identifier = str_repeat('a', 96);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->post(route('cashier.fiscal-documents.manual-submission', $document), [
            'provider' => 'Proveedor de pruebas',
            'external_number' => 'FV-1001',
            'fiscal_identifier' => $identifier,
            'submitted_at' => now()->format('Y-m-d H:i:s'),
            'validation_evidence' => UploadedFile::fake()->create('aceptacion.pdf', 20, 'application/pdf'),
            'verified_externally' => 1,
        ])->assertRedirect(route('cashier.fiscal-documents.index'));

        $document->refresh();
        $this->assertSame('manually_submitted', $document->status);
        $this->assertSame('FV-1001', $document->external_number);
        $this->assertSame($identifier, $document->fiscal_identifier);
        Storage::disk('local')->assertExists($document->validation_evidence_path);
        $this->post(route('cashier.fiscal-documents.hold', $document), ['reason' => 'Intento después del envío'])->assertSessionHasErrors('document');
        $this->assertSame('manually_submitted', $document->fresh()->status);
    }

    public function test_card_payment_is_not_expected_cash_and_invalid_mixed_payment_rolls_back(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000, 'payment_method' => 'mixed', 'payments' => ['cash' => 10000, 'card' => 10000],
        ])->assertSessionHasErrors('payments');
        $this->assertSame('ready', $this->order->fresh()->status);
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000, 'payment_method' => 'mixed', 'payments' => ['cash' => 10000, 'card' => 35000],
        ])->assertSessionHasNoErrors();
        $summary = app(CashierDailyClosureService::class)->dailySummary();
        $this->assertSame(10000.0, $summary['cash_sales']);
        $this->assertSame(45000.0, $summary['total_sales']);
    }

    public function test_manual_submission_cannot_remove_pending_without_manager_and_evidence(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), ['expected_total' => 45000]);
        $document = FiscalDocument::sole();
        $this->post(route('cashier.fiscal-documents.manual-submission', $document), [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'administrator']))
            ->post(route('cashier.fiscal-documents.manual-submission', $document), [
                'provider' => 'Test', 'external_number' => 'FV-1', 'fiscal_identifier' => 'texto cualquiera', 'submitted_at' => now(),
            ])->assertSessionHasErrors(['fiscal_identifier', 'validation_evidence', 'verified_externally']);
        $this->assertTrue($document->fresh()->isUnsent());
    }

    public function test_legacy_payment_requires_audited_reconciliation_without_changing_sale(): void
    {
        $this->order->update(['status' => 'paid', 'inventory_confirmed_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'administrator']))
            ->post(route('cashier.fiscal-documents.reconcile-payment', $this->order), [
                'payments' => ['card' => 45000], 'reason' => 'Verificado extracto del datáfono',
            ])->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(CashierDailyClosureService::class)->dailySummary()['cash_sales']);
        $this->assertSame(45000.0, (float) $this->order->fresh()->total);
        $this->assertDatabaseHas('audit_events', ['action' => 'legacy_payment_reconciled']);
    }

    public function test_operational_pages_render_for_allowed_roles_and_empty_states(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin);
        foreach (['cashier.pos', 'cashier.fiscal-documents.index', 'cook.orders', 'cook.recipes', 'admin.products', 'admin.dashboard', 'waiter.create-order'] as $route) {
            $response = $this->get(route($route))->assertOk();
            if (getenv('ERP_VISUAL_QA') === '1') {
                File::ensureDirectoryExists(storage_path('app/qa'));
                File::put(storage_path('app/qa/'.str_replace('.', '-', $route).'.html'), $response->getContent());
            }
        }
        $this->get(route('cashier.table-detail', 1))->assertOk();
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), ['expected_total' => 45000]);
        $document = FiscalDocument::sole();
        $this->get(route('cashier.fiscal-documents.edit', $document))->assertOk();
    }

    public function test_cash_closure_snapshot_reports_unsent_count_and_value(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000,
            'payment_method' => 'cash',
        ]);
        CashierCashCount::create([
            'fiscal_date' => today(),
            'cashier_id' => $this->cashier->id,
            'sales_counts' => ['50000' => 1],
            'sales_total' => 50000,
            'declared_cash_total' => 50000,
        ]);

        $summary = app(CashierDailyClosureService::class)->dailySummary();
        $this->assertSame(1, $summary['fiscal_pending_count']);
        $this->assertSame(45000.0, $summary['fiscal_pending_total']);
    }

    public function test_missing_pdfs_are_recovered_from_saved_snapshots_and_failure_returns_to_a_valid_page(): void
    {
        $closure = CashierDailyClosure::create([
            'fiscal_date' => today(), 'closed_at' => now(), 'cashier_id' => $this->cashier->id,
            'total_sales' => 45000, 'fiscal_pending_count' => 1, 'fiscal_pending_total' => 45000,
        ]);
        $file = UploadedFile::fake()->create('recovered.pdf', 10, 'application/pdf');
        $this->mock(CashierDailyClosureService::class)->shouldReceive('generatePdf')->once()
            ->withArgs(fn ($saved) => $saved->fiscal_pending_count === 1 && (float) $saved->total_sales === 45000.0)
            ->andReturn($file->getPathname());
        $this->actingAs($this->cashier)->get(route('cashier.cash-closure.pdf', $closure))->assertOk();
        $this->assertSame('45000.00', $closure->fresh()->fiscal_pending_total);

        $report = DailyReportZ::create([
            'fiscal_date' => today(), 'cashier_id' => $this->cashier->id, 'total_sales' => 45000,
            'fiscal_pending_count' => 1, 'fiscal_pending_total' => 45000,
        ]);
        $this->mock(ReportZService::class)->shouldReceive('generatePdf')->once()
            ->withArgs(fn ($data) => $data['fiscal_pending_count'] === 1 && (float) $data['fiscal_pending_total'] === 45000.0)
            ->andReturn($file->getPathname());
        $this->actingAs(User::factory()->create(['role' => 'administrator']))->get(route('admin.daily-reports.pdf', $report))->assertOk();
        $report->update(['pdf_local_path' => null]);
        $this->mock(ReportZService::class)->shouldReceive('generatePdf')->once()->andThrow(new \RuntimeException('Storage unavailable'));
        $this->get(route('admin.daily-reports.pdf', $report))->assertRedirect(route('admin.dashboard'))->assertSessionHas('error');
    }

    public function test_reprinting_a_paid_receipt_never_creates_another_payment_or_fiscal_draft(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), ['expected_total' => 45000]);
        $this->post(route('cashier.reprint', $this->order), ['purpose' => 'receipt'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fiscal_documents', 1);
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(45000.0, app(CashierDailyClosureService::class)->dailySummary()['total_sales']);
    }

    public function test_waiter_cannot_access_fiscal_control(): void
    {
        $waiter = User::factory()->create(['role' => 'waiter']);
        $this->actingAs($waiter)->get(route('cashier.fiscal-documents.index'))->assertForbidden();
    }

    public function test_only_managers_can_make_hold_reason_optional_and_cashier_can_then_hold_without_it(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.pay-order', $this->order), [
            'expected_total' => 45000,
            'payment_method' => 'cash',
        ]);
        $document = FiscalDocument::sole();

        $this->post(route('cashier.fiscal-documents.hold', $document))
            ->assertSessionHasErrors('reason');
        $this->put(route('admin.fiscal-settings.update'), ['require_hold_reason' => 0])
            ->assertForbidden();

        $administrator = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($administrator)->put(route('admin.fiscal-settings.update'), [
            'require_hold_reason' => 0,
        ])->assertRedirect();
        $this->assertFalse(FiscalSetting::requiresHoldReason());

        $this->actingAs($this->cashier)->post(route('cashier.fiscal-documents.hold', $document))
            ->assertRedirect(route('cashier.fiscal-documents.index'));
        $this->assertSame('held_for_correction', $document->fresh()->status);
        $this->assertNull($document->fresh()->hold_reason);
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(45000.0, app(CashierDailyClosureService::class)->dailySummary()['total_sales']);
    }
}
