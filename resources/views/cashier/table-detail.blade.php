@extends('layouts.app')
@section('title', "Mesa #$table — Detalle")
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Mesa #{{ $table }}</h1>
        <p class="page-subtitle">
            @if($order) Estado: <span class="badge badge-{{ $order->status_color }}">{{ $order->status_label }}</span>
            @else Sin orden activa @endif
        </p>
    </div>
    <a href="{{ route('cashier.pos') }}" class="btn btn-ghost">← Volver al Mapa</a>
</div>

@if(!$order)
    <div class="card" style="text-align:center;padding:3rem;">
        <p style="font-size:3rem;">🪑</p>
        <p style="color:var(--text-muted);margin-top:1rem;">Esta mesa está libre.</p>
    </div>
@else
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:1rem;">📝 Detalle de la Orden</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Mesero: {{ $order->waiter->name ?? 'N/A' }} · {{ $order->created_at->format('H:i') }}</p>
        <table class="data-table">
            <thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th>Notas</th><th>Accion</th></tr></thead>
            <tbody>
                @foreach($order->details as $d)
                <tr>
                    <td style="font-weight:600;">{{ $d->product->name }}</td>
                    <td>{{ $d->quantity }}</td>
                    <td>{{ cop($d->unit_price) }}</td>
                    <td>{{ cop($d->subtotal) }}</td>
                    <td style="font-size:0.75rem;color:var(--text-muted);">{{ $d->comments ?? '—' }}</td>
                    <td>
                        @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
                        <form action="{{ route('cashier.order-details.destroy', $d) }}" method="POST" onsubmit="return confirm('¿Eliminar este plato de la mesa?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @if($d->modifiers->count())
                    @foreach($d->modifiers as $m)
                    <tr style="background:rgba(99,102,241,0.05);">
                        <td style="padding-left:2rem;font-size:0.8rem;">+ {{ $m->modifier->name }}</td>
                        <td>{{ $m->quantity }}</td>
                        <td>{{ cop($m->unit_price) }}</td>
                        <td>{{ cop($m->subtotal) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endforeach
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <div class="card" style="margin-bottom:1rem;">
            <h3 style="font-weight:700;margin-bottom:1rem;">💰 Resumen</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;"><span>Subtotal:</span><span>{{ cop($order->subtotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;color:var(--text-muted);"><span>IVA ({{ config('app.tax_rate')*100 }}%):</span><span>{{ cop($order->tax) }}</span></div>
            <div style="display:flex;justify-content:space-between;padding-top:0.75rem;border-top:2px solid var(--border);font-size:1.5rem;font-weight:800;color:var(--accent-hover);"><span>TOTAL:</span><span>{{ cop($order->total) }}</span></div>
        </div>

        @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
            @if($order->isInKitchen())
            <form action="{{ route('cashier.mark-ready', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">✅ Marcar como Listo</button>
            </form>
            @endif

            @if($order->isPending())
            <form action="{{ route('cashier.send-kitchen', $order) }}" method="POST" onsubmit="return confirm('¿Enviar comanda a cocina?')">
                @csrf
                <button type="submit" class="btn btn-warning btn-lg" style="width:100%;justify-content:center;">Enviar comanda a cocina</button>
            </form>
            @endif

            @if($order->isReady() || $order->isPending())
            <form action="{{ route('cashier.pay-order', $order) }}" method="POST" onsubmit="return confirm('¿Confirmar cobro de {{ cop($order->total) }}?')">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">💳 Cobrar Orden</button>
            </form>
            @endif

            <form action="{{ route('cashier.cancel-order', $order) }}" method="POST" onsubmit="return confirm('¿Cancelar esta orden?')">
                @csrf
                <input type="text" name="reason" class="form-input" placeholder="Razón de cancelación..." required style="margin-bottom:0.5rem;">
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center;">❌ Cancelar Orden</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endif
@endsection
