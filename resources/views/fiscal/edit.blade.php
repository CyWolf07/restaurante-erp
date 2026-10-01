@extends('layouts.app')
@section('title', 'Revisión de borrador fiscal')

@push('styles')
<style>
    .fiscal-layout { display:grid; grid-template-columns:minmax(0, 1.4fr) minmax(300px, 0.6fr); gap:1.25rem; align-items:start; }
    .snapshot-row { display:flex; justify-content:space-between; gap:1rem; padding:0.55rem 0; border-bottom:1px solid var(--border); }
    .danger-zone { border-color:rgba(239,68,68,0.5); }
    @media(max-width:900px){ .fiscal-layout { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Borrador fiscal {{ Str::limit($fiscalDocument->id, 8, '') }}</h1>
        <p class="page-subtitle">Estado: {{ $fiscalDocument->status_label }} · Venta interna {{ cop($fiscalDocument->total) }}</p>
    </div>
    <a href="{{ route('cashier.fiscal-documents.index') }}" class="btn btn-ghost">← Volver</a>
</div>

@if($fiscalDocument->isUnsent())
<div class="alert alert-warning">No transmitido. Puedes corregirlo o pulsar ESC para retener el envío sin eliminar la venta.</div>
@else
<div class="alert alert-success">Registrado como enviado manualmente. Este registro es inmutable; cualquier corrección fiscal posterior debe tramitarse en el sistema externo.</div>
@endif

<div class="fiscal-layout">
    <div>
        <div class="card" style="margin-bottom:1rem;">
            <h2 class="card-title" style="margin-bottom:1rem;">Datos autocompletados de la venta</h2>
            <a class="btn btn-ghost" href="{{ route('cashier.fiscal-documents.export', $fiscalDocument) }}">Exportar datos JSON internos (no factura fiscal)</a>
            @if($fiscalDocument->order)
            <form method="POST" action="{{ route('cashier.reprint', $fiscalDocument->order) }}" style="margin-bottom:1rem;">@csrf<input type="hidden" name="purpose" value="receipt"><button class="btn btn-ghost">Reimprimir recibo interno (no es factura electrónica)</button></form>
            @endif
            @foreach(data_get($fiscalDocument->document_snapshot, 'items', []) as $item)
                <div class="snapshot-row">
                    <span>{{ $item['quantity'] }} × {{ $item['description'] }} @if(($item['discount'] ?? 0) > 0)<small>— desc. {{ cop($item['discount']) }}</small>@endif</span>
                    <strong>{{ cop($item['subtotal']) }}</strong>
                </div>
                @foreach($item['modifiers'] ?? [] as $modifier)
                    <div class="snapshot-row" style="padding-left:1rem;color:var(--text-muted);">
                        <span>+ {{ $modifier['description'] }}</span><span>{{ cop($modifier['subtotal']) }}</span>
                    </div>
                @endforeach
            @endforeach
            <div class="snapshot-row"><span>Subtotal</span><strong>{{ cop($fiscalDocument->subtotal) }}</strong></div>
            <div class="snapshot-row"><span>Impuestos</span><strong>{{ cop($fiscalDocument->tax) }}</strong></div>
            <div class="snapshot-row" style="font-size:1.2rem;"><span>Total</span><strong>{{ cop($fiscalDocument->total) }}</strong></div>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Comprador y clasificación</h2>
            <form method="POST" action="{{ route('cashier.fiscal-documents.update', $fiscalDocument) }}">
                @csrf @method('PUT')
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type" class="form-select" @disabled(!$fiscalDocument->isUnsent())>
                            <option value="electronic_invoice" @selected(old('document_type', $fiscalDocument->document_type) === 'electronic_invoice')>Factura electrónica de venta</option>
                            <option value="electronic_pos" @selected(old('document_type', $fiscalDocument->document_type) === 'electronic_pos')>Documento equivalente POS electrónico</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medio de pago</label>
                        <input type="hidden" name="payment_method" value="{{ $fiscalDocument->payment_method }}">
                        <select class="form-select" disabled>
                            @foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','mixed'=>'Mixto','other'=>'Otro'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('payment_method', $fiscalDocument->payment_method) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                    <input type="hidden" name="consumer_final" value="0">
                    <input type="checkbox" name="consumer_final" value="1" id="consumer-final" @checked(old('consumer_final', data_get($fiscalDocument->buyer_data, 'consumer_final', true))) @disabled(!$fiscalDocument->isUnsent())>
                    Consumidor final / comprador no identificado
                </label>
                <div id="buyer-fields" class="grid grid-2">
                    <div class="form-group"><label class="form-label">Tipo identificación</label><select name="buyer_document_type" class="form-select" @disabled(!$fiscalDocument->isUnsent())>@foreach(['CC','NIT','CE','PP'] as $type)<option @selected(data_get($fiscalDocument->buyer_data, 'document_type')===$type)>{{ $type }}</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label">Número</label><input name="buyer_document_number" class="form-input" value="{{ old('buyer_document_number', data_get($fiscalDocument->buyer_data, 'document_number')) }}" @disabled(!$fiscalDocument->isUnsent())></div>
                    <div class="form-group"><label class="form-label">Nombre o razón social</label><input name="buyer_name" class="form-input" value="{{ old('buyer_name', data_get($fiscalDocument->buyer_data, 'name')) }}" @disabled(!$fiscalDocument->isUnsent())></div>
                    <div class="form-group"><label class="form-label">Correo</label><input type="email" name="buyer_email" class="form-input" value="{{ old('buyer_email', data_get($fiscalDocument->buyer_data, 'email')) }}" @disabled(!$fiscalDocument->isUnsent())></div>
                    <div class="form-group"><label class="form-label">Teléfono</label><input name="buyer_phone" class="form-input" value="{{ old('buyer_phone', data_get($fiscalDocument->buyer_data, 'phone')) }}" @disabled(!$fiscalDocument->isUnsent())></div>
                    <div class="form-group"><label class="form-label">Dirección</label><input name="buyer_address" class="form-input" value="{{ old('buyer_address', data_get($fiscalDocument->buyer_data, 'address')) }}" @disabled(!$fiscalDocument->isUnsent())></div>
                </div>
                <div class="form-group"><label class="form-label">Notas internas</label><textarea name="notes" class="form-textarea" rows="3" @disabled(!$fiscalDocument->isUnsent())>{{ old('notes', $fiscalDocument->notes) }}</textarea></div>
                @if($fiscalDocument->isUnsent())<button class="btn btn-primary" type="submit">Guardar correcciones sin enviar</button>@endif
            </form>
        </div>
    </div>

    <div>
        @if($fiscalDocument->isUnsent())
        <div class="card danger-zone" style="margin-bottom:1rem;">
            <h2 class="card-title">Detener antes del envío</h2>
            <p class="page-subtitle" style="margin:0.5rem 0 1rem;">ESC ejecuta esta acción. No elimina la venta y seguirá sumando como pendiente.</p>
            <form id="hold-form" method="POST" action="{{ route('cashier.fiscal-documents.hold', $fiscalDocument) }}">
                @csrf
                <label class="form-label" for="hold-reason">Motivo del error o retención {{ $requireHoldReason ? '(obligatorio)' : '(opcional)' }}</label>
                <textarea id="hold-reason" name="reason" class="form-textarea" rows="3" @if($requireHoldReason) required @endif minlength="5">{{ old('reason', $fiscalDocument->hold_reason) }}</textarea>
                <button class="btn btn-danger" type="submit" style="width:100%;margin-top:0.75rem;">ESC · Cancelar envío y retener</button>
            </form>
            @if($fiscalDocument->status === 'held_for_correction')
            <form method="POST" action="{{ route('cashier.fiscal-documents.reactivate', $fiscalDocument) }}" style="margin-top:0.75rem;">@csrf<button class="btn btn-ghost" type="submit" style="width:100%;">Reactivar después de corregir</button></form>
            @endif
        </div>

        <div class="card">
            <h2 class="card-title">Conciliar envío manual (admin/programador)</h2>
            <p class="page-subtitle" style="margin:0.5rem 0 1rem;">Úsalo solo después de que el sistema externo confirme el documento. Se exige evidencia para que deje de contar como pendiente.</p>
            @if(in_array(auth()->user()->role, ['administrator', 'programmer'], true))
            <form method="POST" enctype="multipart/form-data" action="{{ route('cashier.fiscal-documents.manual-submission', $fiscalDocument) }}" onsubmit="return confirm('¿Verificaste el documento y su aceptación en el proveedor o en DIAN?')">
                @csrf
                <div class="form-group"><label class="form-label">Proveedor o sistema</label><input name="provider" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Número fiscal externo</label><input name="external_number" class="form-input" required></div>
                <div class="form-group"><label class="form-label">CUFE / CUDE (96 caracteres hexadecimales)</label><input name="fiscal_identifier" class="form-input" minlength="96" maxlength="96" pattern="[0-9a-fA-F]{96}" required></div>
                <div class="form-group"><label class="form-label">Soporte de aceptación (PDF, XML o TXT, máximo 10 MB)</label><input type="file" name="validation_evidence" class="form-input" accept=".pdf,.xml,.txt" required></div>
                <label><input type="checkbox" name="verified_externally" value="1" required> Verifiqué la aceptación externa, el número, el comprador y el total; el ERP no consulta DIAN automáticamente.</label>
                <div class="form-group"><label class="form-label">Fecha y hora de validación</label><input type="datetime-local" name="submitted_at" class="form-input" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                <button class="btn btn-success" type="submit" style="width:100%;">Registrar evidencia de envío</button>
            </form>
            @else
            <p>Entrega el soporte al administrador para que verifique y concilie el documento. Mientras tanto seguirá contando como pendiente.</p>
            @endif
        </div>
        @else
        <div class="card">
            <h2 class="card-title">Evidencia registrada</h2>
            <a class="btn btn-ghost" href="{{ route('cashier.fiscal-documents.evidence', $fiscalDocument) }}">Descargar soporte privado</a>
            <div class="snapshot-row"><span>Proveedor</span><strong>{{ $fiscalDocument->provider }}</strong></div>
            <div class="snapshot-row"><span>Número</span><strong>{{ $fiscalDocument->external_number }}</strong></div>
            <div class="snapshot-row"><span>CUFE/CUDE</span><strong style="word-break:break-all;">{{ $fiscalDocument->fiscal_identifier }}</strong></div>
            <div class="snapshot-row"><span>Validado</span><strong>{{ $fiscalDocument->submitted_at?->format('d/m/Y H:i') }}</strong></div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if($fiscalDocument->isUnsent())
<script>
const consumerFinal = document.getElementById('consumer-final');
const buyerFields = document.getElementById('buyer-fields');
const toggleBuyer = () => { buyerFields.hidden = consumerFinal.checked; };
consumerFinal.addEventListener('change', toggleBuyer);
toggleBuyer();

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    event.preventDefault();
    const reason = document.getElementById('hold-reason');
    if ({{ $requireHoldReason ? 'true' : 'false' }} && reason.value.trim().length < 5) {
        reason.focus();
        alert('Escribe primero el motivo del error o de la retención.');
        return;
    }
    if (confirm('¿Cancelar el envío antes de transmitir? La venta seguirá activa y contará como pendiente.')) {
        document.getElementById('hold-form').submit();
    }
});
</script>
@endif
@endpush
