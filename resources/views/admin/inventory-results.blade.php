@extends('layouts.app')
@section('title', 'Resultados del Conteo')
@push('styles')
<style>
    .critical-row td { background: rgba(239,68,68,0.1) !important; }
    .critical-row { animation: pulse-danger 1.5s ease-in-out infinite; }
    .semaforo { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
</style>
@endpush
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📋 Resultados del Conteo</h1>
        <p class="page-subtitle">
            {{ $inventory->recorded_at->format('d/m/Y H:i') }} — {{ $inventory->admin->name }}
        </p>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Dashboard</a>
</div>

@php
    $totalImpact = collect($dashboard)->sum('financial_impact');
    $criticalCount = collect($dashboard)->where('criticality', 'red')->count();
@endphp

<div class="grid grid-3" style="margin-bottom:1.5rem;">
    <div class="stat-card">
        <div class="stat-value">${{ number_format($totalImpact, 2) }}</div>
        <div class="stat-label">Impacto financiero total</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--danger);">{{ $criticalCount }}</div>
        <div class="stat-label">Fugas críticas (rojo)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ count($dashboard) }}</div>
        <div class="stat-label">Insumos analizados</div>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th></th>
                <th>Insumo</th>
                <th>Teórico</th>
                <th>Físico</th>
                <th>Diferencia</th>
                <th>Desv. %</th>
                <th>Impacto $</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dashboard as $row)
            <tr class="{{ $row['criticality'] === 'red' ? 'critical-row' : '' }}">
                <td>
                    <span class="semaforo" style="background:{{ $row['criticality_color'] }};"></span>
                </td>
                <td><strong>{{ $row['supply_name'] }}</strong></td>
                <td>{{ number_format($row['theoretical_stock'], 2) }} {{ $row['unit_type'] }}</td>
                <td>{{ number_format($row['physical_stock'], 2) }}</td>
                <td style="color:{{ $row['difference'] < 0 ? 'var(--danger)' : 'var(--success)' }};">
                    {{ $row['difference'] >= 0 ? '+' : '' }}{{ number_format($row['difference'], 2) }}
                </td>
                <td>{{ number_format($row['deviation_percentage'], 2) }}%</td>
                <td><strong>${{ number_format($row['financial_impact'], 2) }}</strong></td>
                <td>
                    <span class="badge badge-{{ $row['criticality'] === 'green' ? 'green' : ($row['criticality'] === 'yellow' ? 'yellow' : 'red') }}">
                        {{ $row['criticality_label'] }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
</div>
@endsection
