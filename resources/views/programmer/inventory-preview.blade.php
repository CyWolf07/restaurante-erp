@extends('layouts.app')
@section('title', 'Revisión de importación')
@section('content')
<section class="card">
    <h1>Revisar {{ $upload->original_name }}</h1>
    <p>Filas: {{ $result['total_rows'] }}. Nuevos: {{ $result['created'] }}.
        Actualizaciones: {{ $result['updated'] }}. Omitidos: {{ $result['skipped'] }}.</p>
    <p>Los artículos existentes conservan sus existencias, unidad, mínimos y estado. El stock del CSV se usa únicamente al crear artículos nuevos.</p>
    @if($result['errors'])
        <p>No se aplicó ningún cambio. Corrige el archivo y vuelve a cargarlo.</p>
        <ul>@foreach($result['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
    @elseif($upload->imported_at)
        <p>Este lote ya fue importado y se conserva para consulta.</p>
    @else
        <form method="POST" action="{{ route('programmer.inventory-import.import-stored', $upload) }}">
            @csrf
            <input type="hidden" name="update_existing" value="{{ $updateExisting ? 1 : 0 }}">
            <input type="hidden" name="confirm" value="1">
            <button class="btn btn-primary" type="submit">Confirmar importación</button>
        </form>
    @endif
    <a href="{{ route('programmer.inventory-import') }}" class="btn btn-ghost">Volver a archivos</a>
</section>
@endsection
