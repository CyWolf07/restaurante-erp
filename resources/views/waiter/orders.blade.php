@extends('layouts.app')
@section('title', 'Mis Órdenes — Mesero')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📋 Mis Órdenes Activas</h1>
        <p class="page-subtitle">Órdenes asignadas a ti</p>
    </div>
    <a href="{{ route('waiter.create-order') }}" class="btn btn-primary">➕ Nueva Orden</a>
</div>

@if($orders->isEmpty())
    <div class="card" style="text-align:center;padding:3rem;">
        <p style="font-size:3rem;margin-bottom:1rem;">🍽️</p>
        <p style="color:var(--text-muted);">No tienes órdenes activas.</p>
        <a href="{{ route('waiter.create-order') }}" class="btn btn-primary" style="margin-top:1rem;">Crear Orden</a>
    </div>
@else
<div class="grid grid-3">
    @foreach($orders as $order)
    <div class="card">
        <div class="card-header">
            <div>
                <span style="font-size:1.5rem;font-weight:800;">{{ $order->restaurantTable?->display_name ?? ('Mesa #'.$order->table_number) }}</span>
                <span class="badge badge-{{ $order->status_color }}" style="margin-left:0.5rem;">{{ $order->status_label }}</span>
            </div>
        </div>
        <div style="margin-bottom:1rem;">
            @foreach($order->details as $detail)
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--border);font-size:0.85rem;">
                <span>{{ $detail->quantity }}x {{ $detail->product->name }}</span>
                <span style="color:var(--text-muted);">{{ cop($detail->subtotal) }}</span>
            </div>
            @endforeach
        </div>
        @if($order->kitchen_sent_at)
        <p style="font-size:0.75rem;color:var(--text-muted);margin:0.5rem 0;">
            Enviado a cocina: {{ $order->kitchenSentBy?->name ?? $order->waiter?->name }} · {{ $order->kitchen_sent_at->format('H:i') }}
        </p>
        @endif
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.75rem;border-top:1px solid var(--border);flex-wrap:wrap;gap:0.5rem;">
            <span style="font-weight:800;font-size:1.1rem;">{{ cop($order->total) }}</span>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <form action="{{ route('waiter.print-preticket', $order) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm" title="Reimprimir cuenta provisional">🖨️ Pre-ticket</button>
                </form>
                @if($order->isPending())
                <form action="{{ route('waiter.send-kitchen', $order) }}" method="POST" onsubmit="return confirm('¿Enviar orden a cocina?')">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">🔥 Enviar a Cocina</button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
