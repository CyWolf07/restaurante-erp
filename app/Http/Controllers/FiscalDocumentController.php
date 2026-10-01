<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Models\FiscalSetting;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\FiscalDocumentService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FiscalDocumentController extends Controller
{
    public function index(Request $request, FiscalDocumentService $service)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['unsent', 'pending_review', 'held_for_correction', 'manually_submitted'])],
            'date' => 'nullable|date',
        ]);

        $query = FiscalDocument::with(['order.restaurantTable', 'heldBy', 'submittedBy'])->latest();
        match ($data['status'] ?? 'unsent') {
            'unsent' => $query->unsent(),
            'pending_review' => $query->where('status', 'pending_review'),
            'held_for_correction' => $query->where('status', 'held_for_correction'),
            'manually_submitted' => $query->where('status', 'manually_submitted'),
        };
        if (! empty($data['date'])) {
            $query->whereDate('created_at', $data['date']);
        }

        $documents = $query->paginate(25)->withQueryString();
        $unsentSummary = $service->unsentSummary();
        $unreconciledOrders = Order::paid()->whereDoesntHave('fiscalDocument');
        $historicalUnreconciled = ['count' => (clone $unreconciledOrders)->count(), 'total' => (float) $unreconciledOrders->sum('total')];
        $legacyPayments = Order::paid()->whereNull('payment_breakdown')->latest()->paginate(10, ['*'], 'legacy_page');
        $requireHoldReason = FiscalSetting::requiresHoldReason();

        return view('fiscal.index', compact('documents', 'unsentSummary', 'requireHoldReason', 'historicalUnreconciled', 'legacyPayments'));
    }

    public function edit(FiscalDocument $fiscalDocument)
    {
        $fiscalDocument->load(['order.details.product', 'order.details.modifiers.modifier', 'heldBy', 'submittedBy']);
        $requireHoldReason = FiscalSetting::requiresHoldReason();

        return view('fiscal.edit', compact('fiscalDocument', 'requireHoldReason'));
    }

    public function update(Request $request, FiscalDocument $fiscalDocument)
    {
        $this->assertUnsent($fiscalDocument);
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['electronic_invoice', 'electronic_pos'])],
            'payment_method' => ['required', Rule::in([$fiscalDocument->payment_method])],
            'consumer_final' => 'nullable|boolean',
            'buyer_document_type' => ['nullable', Rule::in(['NIT', 'CC', 'CE', 'PP'])],
            'buyer_document_number' => 'nullable|string|max:30',
            'buyer_name' => 'nullable|string|max:160',
            'buyer_email' => 'nullable|email|max:190',
            'buyer_phone' => 'nullable|string|max:30',
            'buyer_address' => 'nullable|string|max:250',
            'notes' => 'nullable|string|max:2000',
        ]);

        $consumerFinal = $request->boolean('consumer_final');
        if (! $consumerFinal && (empty($data['buyer_document_number']) || empty($data['buyer_name']))) {
            throw ValidationException::withMessages([
                'buyer_document_number' => 'Para un comprador identificado se requiere documento y nombre o razón social.',
            ]);
        }

        DB::transaction(function () use ($fiscalDocument, $data, $consumerFinal) {
            $before = $fiscalDocument->only(['document_type', 'payment_method', 'buyer_data', 'notes']);
            $fiscalDocument->update([
                'document_type' => $data['document_type'],
                'payment_method' => $data['payment_method'],
                'buyer_data' => [
                    'consumer_final' => $consumerFinal,
                    'document_type' => $consumerFinal ? null : ($data['buyer_document_type'] ?? null),
                    'document_number' => $consumerFinal ? null : ($data['buyer_document_number'] ?? null),
                    'name' => $consumerFinal ? null : ($data['buyer_name'] ?? null),
                    'email' => $data['buyer_email'] ?? null,
                    'phone' => $data['buyer_phone'] ?? null,
                    'address' => $data['buyer_address'] ?? null,
                ],
                'notes' => $data['notes'] ?? null,
            ]);
            app(AuditService::class)->record('fiscal_document', $fiscalDocument->id, 'draft_updated', [
                'before' => $before,
                'after' => $fiscalDocument->fresh()->only(['document_type', 'payment_method', 'buyer_data', 'notes']),
            ]);
        });

        return back()->with('success', 'Borrador fiscal actualizado. Aún no se ha transmitido.');
    }

    public function hold(Request $request, FiscalDocument $fiscalDocument)
    {
        $this->assertUnsent($fiscalDocument);
        $requireHoldReason = FiscalSetting::requiresHoldReason();
        $data = $request->validate([
            'reason' => [$requireHoldReason ? 'required' : 'nullable', 'string', 'min:5', 'max:1000'],
        ]);
        $reason = trim($data['reason'] ?? '');

        $fiscalDocument->update([
            'status' => 'held_for_correction',
            'hold_reason' => $reason !== '' ? $reason : null,
            'held_by' => Auth::id(),
            'held_at' => now(),
        ]);
        app(AuditService::class)->record('fiscal_document', $fiscalDocument->id, 'held_before_transmission', [
            'reason' => $reason !== '' ? $reason : null,
            'total' => $fiscalDocument->total,
        ]);

        return redirect()->route('cashier.fiscal-documents.index')
            ->with('warning', 'Envío cancelado antes de transmitir. El documento sigue activo y pendiente en los reportes.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['require_hold_reason' => 'required|boolean']);
        $before = FiscalSetting::requiresHoldReason();
        FiscalSetting::updateOrCreate(['id' => 1], ['require_hold_reason' => (bool) $data['require_hold_reason']]);
        app(AuditService::class)->record('fiscal_settings', '1', 'hold_reason_requirement_updated', [
            'before' => $before,
            'after' => (bool) $data['require_hold_reason'],
        ]);

        return back()->with('success', 'Configuración del motivo de retención actualizada.');
    }

    public function reactivate(FiscalDocument $fiscalDocument)
    {
        $fiscalDocument->refresh();
        if ($fiscalDocument->status !== 'held_for_correction') {
            throw ValidationException::withMessages(['document' => 'Solo se puede reactivar un borrador retenido.']);
        }

        $fiscalDocument->update(['status' => 'pending_review']);
        app(AuditService::class)->record('fiscal_document', $fiscalDocument->id, 'draft_reactivated', []);

        return redirect()->route('cashier.fiscal-documents.edit', $fiscalDocument)
            ->with('success', 'Borrador reactivado para revisión. Todavía no se ha transmitido.');
    }

    public function markManuallySubmitted(Request $request, FiscalDocument $fiscalDocument)
    {
        abort_unless(in_array(Auth::user()->role, ['administrator', 'programmer'], true), 403);
        $this->assertUnsent($fiscalDocument);
        $request->merge(['fiscal_identifier' => strtolower(trim((string) $request->input('fiscal_identifier', '')))]);
        $data = $request->validate([
            'provider' => 'required|string|max:100',
            'external_number' => ['required', 'string', 'max:100', Rule::unique('fiscal_documents')->ignore($fiscalDocument->id)],
            'fiscal_identifier' => ['required', 'string', 'regex:/\A[0-9a-fA-F]{96}\z/', Rule::unique('fiscal_documents')->ignore($fiscalDocument->id)],
            'validation_evidence' => 'required|file|mimes:pdf,xml,txt|max:10240',
            'verified_externally' => 'accepted',
            'submitted_at' => 'required|date|before_or_equal:now',
        ]);

        $evidencePath = $request->file('validation_evidence')->store('fiscal_evidence', 'local');
        if (! $evidencePath) {
            throw ValidationException::withMessages(['validation_evidence' => 'No se pudo guardar el soporte. El documento sigue pendiente.']);
        }
        $fiscalDocument->update([
            'status' => 'manually_submitted',
            'provider' => $data['provider'],
            'external_number' => $data['external_number'],
            'fiscal_identifier' => $data['fiscal_identifier'],
            'submitted_by' => Auth::id(),
            'submitted_at' => $data['submitted_at'],
            'validation_evidence_path' => $evidencePath,
        ]);
        app(AuditService::class)->record('fiscal_document', $fiscalDocument->id, 'manual_submission_confirmed', [
            'provider' => $data['provider'],
            'external_number' => $data['external_number'],
            'fiscal_identifier' => $data['fiscal_identifier'],
        ]);

        return redirect()->route('cashier.fiscal-documents.index')
            ->with('success', 'Envío conciliado por responsable con soporte adjunto. No constituye verificación automática ante DIAN.');
    }

    private function assertUnsent(FiscalDocument $document): void
    {
        $document->refresh();
        if (! $document->isUnsent()) {
            throw ValidationException::withMessages([
                'document' => 'El documento ya fue registrado como enviado y no puede modificarse ni cancelarse con ESC.',
            ]);
        }
    }

    public function evidence(FiscalDocument $fiscalDocument)
    {
        abort_unless($fiscalDocument->validation_evidence_path && Storage::disk('local')->exists($fiscalDocument->validation_evidence_path), 404, 'Soporte no disponible.');

        return Storage::disk('local')->download($fiscalDocument->validation_evidence_path);
    }

    public function export(FiscalDocument $fiscalDocument)
    {
        return response()->streamDownload(function () use ($fiscalDocument) {
            echo json_encode([
                'warning' => 'BORRADOR INTERNO: no es XML fiscal, no tiene firma ni se transmite a DIAN.',
                'status' => $fiscalDocument->status,
                'document_type' => $fiscalDocument->document_type,
                'buyer' => $fiscalDocument->buyer_data,
                'sale_snapshot' => $fiscalDocument->document_snapshot,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, 'BORRADOR_NO_FISCAL_'.$fiscalDocument->id.'.json', ['Content-Type' => 'application/json']);
    }

    public function reconcilePayment(Request $request, Order $order)
    {
        abort_unless(in_array(Auth::user()->role, ['administrator', 'programmer'], true), 403);
        $order->refresh();
        if (! $order->isPaid() || $order->payment_breakdown !== null) {
            throw ValidationException::withMessages(['order' => 'Solo se pueden clasificar cobros históricos sin medio registrado.']);
        }
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000', 'payments' => 'required|array:cash,card,transfer,other', 'payments.*' => 'required|numeric|decimal:0,2|min:0']);
        $sum = BigDecimal::zero();
        foreach ($data['payments'] as $amount) {
            $sum = $sum->plus((string) $amount);
        }
        if (! $sum->isEqualTo($order->total)) {
            throw ValidationException::withMessages(['payments' => 'El desglose debe coincidir con el total histórico; no se modifica la venta.']);
        }
        $order->update(['payment_breakdown' => $data['payments']]);
        app(AuditService::class)->record('order', $order->id, 'legacy_payment_reconciled', ['reason' => $data['reason'], 'payments' => $data['payments']]);

        return back()->with('success', 'Medios de pago históricos conciliados sin cambiar la venta ni los cierres anteriores.');
    }
}
