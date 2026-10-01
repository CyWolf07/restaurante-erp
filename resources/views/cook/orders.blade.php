@extends('layouts.app')
@section('title', 'Cola de cocina')
@section('content')
<div class="page-header"><div><h1 class="page-title">Cola de cocina</h1><p class="page-subtitle">Pedidos enviados por meseros o caja. Marcar listo avisa en el POS; no descuenta inventario otra vez.</p></div><a class="btn btn-ghost" href="{{ route('cook.orders') }}">Actualizar</a></div>
<div class="grid grid-2">
@forelse($orders as $order)
<div class="card">
    <h2 class="card-title">{{ $order->restaurantTable?->display_name ?? 'Mesa '.$order->table_number }} · {{ $order->status_label }}</h2>
    <p>Mesero: {{ $order->waiter?->name }} · {{ $order->kitchen_sent_at?->format('H:i') }}</p>
    @foreach($order->details as $detail)
    <div style="margin:0.75rem 0;"><strong>{{ $detail->quantity }} × {{ $detail->product?->name }}</strong>
        @if($detail->comments)<p>{{ $detail->comments }}</p>@endif
        @foreach($detail->modifiers as $modifier)<p>+ {{ $modifier->modifier?->name }}</p>@endforeach
    </div>
    @endforeach
    @if($order->isInKitchen())<form method="POST" action="{{ route('cook.mark-ready', $order) }}">@csrf<button class="btn btn-success">Marcar listo para servir / cobrar</button></form>@endif
</div>
@empty
<div class="card">No hay pedidos enviados a cocina.</div>
@endforelse
</div>
{{ $orders->links('vendor.pagination.erp') }}
@endsection
