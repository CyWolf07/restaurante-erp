@extends('layouts.app')
@section('title', 'Producción por lotes')
@section('content')
<section class="page-header"><h1>Producción por lotes</h1></section>
<section class="card" style="margin-bottom:1rem">
    <h2>Nueva orden</h2>
    <p>Selecciona una ficha con ingredientes por cada unidad del elaborado de salida. Crea primero el insumo elaborado en bodega. Al cerrar se consumirán los ingredientes previstos y se registrará la cantidad real obtenida.</p>
    <form method="POST" action="{{ route('production.store') }}" class="grid grid-3">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) Illuminate\Support\Str::uuid()) }}">
        <label>Ficha de receta<select name="product_id" class="form-select" required>
            @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
        </select></label>
        <label>Elaborado de salida<select name="output_supply_id" class="form-select" required>
            @foreach($supplies as $supply)<option value="{{ $supply->id }}">{{ $supply->name }} ({{ $supply->unit_label }})</option>@endforeach
        </select></label>
        <label>Cantidad prevista<input type="number" name="planned_quantity" class="form-input" min="0.0001" max="999999" step="0.0001" required value="{{ old('planned_quantity', 1) }}"></label>
        <button class="btn btn-primary">Crear borrador</button>
    </form>
</section>
@foreach($orders as $order)
@php $snapshot = $order->recipe_snapshot; @endphp
<section class="card" style="margin-bottom:1rem">
    <h2>{{ $snapshot['name'] }} — {{ ['draft'=>'Borrador', 'completed'=>'Terminado', 'cancelled'=>'Anulado'][$order->status] }}</h2>
    <p>Lote {{ $order->id }} · Receta versión {{ $snapshot['version'] }} · {{ $order->creator?->name }}</p>
    <p>Salida: {{ $snapshot['output_name'] }}. Previsto: {{ $order->planned_quantity }} {{ $snapshot['output_unit'] }}.
        @if($order->status === 'completed') Real: {{ $order->actual_quantity }}. Rendimiento: {{ number_format(100 * $order->actual_quantity / $order->planned_quantity, 1) }} %. Costo del lote: {{ cop($order->total_cost) }}. @endif
    </p>
    <details><summary>Ingredientes y preparación conservados</summary>
        <ul>@foreach($snapshot['ingredients'] as $ingredient)<li>{{ $ingredient['name'] }}: {{ $ingredient['quantity'] }} {{ $ingredient['unit_type'] }}</li>@endforeach</ul>
        <p style="white-space:pre-wrap">{{ $snapshot['instructions'] }}</p>
    </details>
    @if($order->status === 'draft')
    <form method="POST" action="{{ route('production.complete', $order) }}" style="margin-top:1rem">
        @csrf
        <label>Cantidad real obtenida<input type="number" name="actual_quantity" class="form-input" min="0.0001" max="999999" step="0.0001" required></label>
        <button class="btn btn-primary">Cerrar lote y registrar movimientos</button>
    </form>
    <form method="POST" action="{{ route('production.cancel', $order) }}" style="margin-top:1rem">
        @csrf
        <input name="reason" class="form-input" required minlength="5" maxlength="500" placeholder="Motivo de anulación del borrador">
        <button class="btn btn-ghost">Anular borrador</button>
    </form>
    @elseif($order->status === 'cancelled')<p>Motivo: {{ $order->cancellation_reason }}</p>@endif
</section>
@endforeach
{{ $orders->links() }}
@endsection
