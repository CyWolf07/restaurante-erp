@extends('layouts.app')
@section('title', 'Historial de cambios')
@section('content')
<section class="page-header"><h1>Historial de cambios</h1></section>
<section class="card">
    <form method="GET" class="grid grid-3">
        <label>Área<select name="entity" class="form-select">
            <option value="">Todas</option>
            @foreach(['user'=>'Personal', 'product'=>'Productos y recetas', 'supply'=>'Catálogo de bodega'] as $key=>$label)
                <option value="{{ $key }}" @selected(request('entity') === $key)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label>Identificador<input class="form-input" name="entity_id" value="{{ request('entity_id') }}"></label>
        <button class="btn btn-primary">Filtrar</button>
    </form>
    <p>Los movimientos de existencias conservan además su responsable y motivo en el registro de inventario. Las credenciales no se incluyen en este historial.</p>
    @forelse($events as $event)
        <article style="border-bottom:1px solid var(--border);padding:1rem 0">
            <strong>{{ $event->created_at }} · {{ $event->actor ?? 'Sistema' }} · {{ $event->action }}</strong>
            <p>{{ $event->entity_type }} · {{ $event->entity_id }}</p>
            <details><summary>Ver cambios</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode(json_decode($event->data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
        </article>
    @empty<p>Todavía no hay cambios registrados.</p>@endforelse
    {{ $events->links() }}
</section>
@endsection
