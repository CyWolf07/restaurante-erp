@extends('layouts.app')
@section('title', 'Verificación de Integridad')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🔍 Verificación de Integridad (Dry-Run)</h1>
        <p class="page-subtitle">Comparación stock actual vs suma histórica de logs — sin aplicar cambios</p>
    </div>
    <a href="{{ route('programmer.panel') }}" class="btn btn-ghost">← Panel</a>
</div>

@php $needsRepair = collect($results)->where('needs_repair', true); @endphp

@if($needsRepair->isEmpty())
    <div class="alert alert-success">✅ Todos los insumos están consistentes.</div>
@else
    <div class="alert alert-warning">⚠️ {{ $needsRepair->count() }} insumo(s) con desfase detectado.</div>
@endif

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Insumo</th>
                <th>Stock actual</th>
                <th>Calculado (logs)</th>
                <th>Desfase</th>
                <th>¿Reparar?</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $row)
            <tr style="{{ $row['needs_repair'] ? 'background:rgba(245,158,11,0.08);' : '' }}">
                <td><strong>{{ $row['supply_name'] }}</strong></td>
                <td>{{ number_format($row['current_stock'], 4) }}</td>
                <td>{{ number_format($row['calculated_stock'], 4) }}</td>
                <td style="color:{{ abs($row['difference']) > 0.0001 ? 'var(--danger)' : 'var(--success)' }};">
                    {{ number_format($row['difference'], 4) }}
                </td>
                <td>
                    @if($row['needs_repair'])
                        <span class="badge badge-yellow">Sí</span>
                    @else
                        <span class="badge badge-green">OK</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
