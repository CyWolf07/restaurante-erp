@extends('layouts.app')
@section('title', 'Control de facturación pendiente')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🧾 Control fiscal previo al envío</h1>
        <p class="page-subtitle">Borradores locales. Ningún documento de esta bandeja se transmite automáticamente.</p>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="stat-card">
        <div class="stat-value">{{ $unsentSummary['count'] }}</div>
        <div class="stat-label">Documentos sin enviar</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ cop($unsentSummary['total']) }}</div>
        <div class="stat-label">Valor total pendiente de envío</div>
    </div>
</div>

<div class="alert alert-warning">
    ⚠️ “Retener” cancela el intento antes de transmitir, pero conserva la venta y su valor en este control. Un documento validado externamente no puede cancelarse aquí.
    Revisa con tu contador los plazos de expedición y el procedimiento de contingencia. Este borrador local no es una factura fiscal ni permite aplazar indefinidamente su emisión.
</div>
@if($historicalUnreconciled['count'] > 0)
<div class="alert alert-warning">Hay {{ $historicalUnreconciled['count'] }} ventas anteriores sin documento fiscal local por {{ cop($historicalUnreconciled['total']) }}. Requieren conciliación con el proveedor: no se consideran automáticamente facturas sin enviar ni se deben facturar dos veces.</div>
@endif

@if(auth()->user()->isManager())
@if($legacyPayments->isNotEmpty())
<div class="card" style="margin-bottom:1rem;"><h2 class="card-title">Cobros históricos sin medio de pago</h2><p>No se asumen en efectivo. Conciliar con el arqueo / proveedor antes del cierre.</p>
@foreach($legacyPayments as $legacy)
<details style="margin-top:1rem;"><summary>Mesa {{ $legacy->table_number }} · {{ $legacy->created_at->format('d/m/Y') }} · {{ cop($legacy->total) }}</summary>
<form method="POST" action="{{ route('cashier.fiscal-documents.reconcile-payment', $legacy) }}">@csrf
<div class="grid grid-2">@foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','other'=>'Otro'] as $key=>$label)<label class="form-label">{{ $label }}<input type="number" class="form-input" name="payments[{{ $key }}]" min="0" step="0.01" value="0" required></label>@endforeach</div>
<label class="form-label">Motivo y referencia del soporte<input class="form-input" name="reason" minlength="5" required></label><button class="btn btn-primary">Conciliar medios de pago</button></form></details>
@endforeach
{{ $legacyPayments->links('vendor.pagination.erp') }}
</div>
@endif
<div class="card" style="margin-bottom:1.25rem;">
    <h2 class="card-title" style="margin-bottom:0.5rem;">Configuración de retenciones</h2>
    <p class="page-subtitle" style="margin-bottom:1rem;">Define si al detener el envío de un borrador se debe escribir un motivo. El cambio no altera las ventas ni los documentos ya retenidos.</p>
    <form method="POST" action="{{ route('admin.fiscal-settings.update') }}" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        @csrf @method('PUT')
        <select name="require_hold_reason" class="form-select" style="width:auto;max-width:100%;" aria-label="Exigir motivo al retener">
            <option value="1" @selected($requireHoldReason)>Exigir motivo</option>
            <option value="0" @selected(!$requireHoldReason)>Motivo opcional</option>
        </select>
        <button type="submit" class="btn btn-primary">Guardar configuración</button>
    </form>
</div>
@endif

<div class="card">
    <form method="GET" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="fiscal-status">Estado</label>
            <select class="form-select" id="fiscal-status" name="status">
                <option value="unsent" @selected(request('status', 'unsent') === 'unsent')>Todos sin enviar</option>
                <option value="pending_review" @selected(request('status') === 'pending_review')>Pendientes de revisión</option>
                <option value="held_for_correction" @selected(request('status') === 'held_for_correction')>Retenidos para corregir</option>
                <option value="manually_submitted" @selected(request('status') === 'manually_submitted')>Enviados manualmente</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="fiscal-date">Fecha</label>
            <input class="form-input" id="fiscal-date" type="date" name="date" value="{{ request('date') }}">
        </div>
        <button class="btn btn-primary" type="submit">Filtrar</button>
    </form>

    <table class="data-table">
        <thead><tr><th>Orden</th><th>Fecha</th><th>Estado</th><th>Comprador</th><th>Total</th><th>Acción</th></tr></thead>
        <tbody>
        @forelse($documents as $document)
            <tr>
                <td>{{ $document->order?->restaurantTable?->display_name ?? 'Mesa '.($document->order?->table_number ?? '—') }}</td>
                <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                <td><span class="badge {{ $document->status === 'held_for_correction' ? 'badge-red' : ($document->isUnsent() ? 'badge-yellow' : 'badge-green') }}">{{ $document->status_label }}</span></td>
                <td>{{ data_get($document->buyer_data, 'consumer_final') ? 'Consumidor final' : (data_get($document->buyer_data, 'name') ?: 'Por completar') }}</td>
                <td><strong>{{ cop($document->total) }}</strong></td>
                <td><a class="btn btn-ghost btn-sm" href="{{ route('cashier.fiscal-documents.edit', $document) }}">Revisar</a></td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No hay documentos para el filtro seleccionado.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $documents->links('vendor.pagination.erp') }}
</div>
@endsection
