@extends('layouts.app')
@section('title', 'Panel del Programador')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">⚙️ Panel de Resiliencia</h1>
        <p class="page-subtitle">Mantenimiento de infraestructura local</p>
    </div>
    <a href="{{ route('programmer.integrity-check') }}" class="btn btn-ghost">Verificar integridad (dry-run)</a>
</div>

@php
    $diskUsed = $metrics['disk_used_pct'] ?? 0;
    $diskClass = $diskUsed > 90 ? 'danger' : ($diskUsed > 75 ? 'warning' : 'success');
@endphp

<div class="grid grid-3" style="margin-bottom:1.5rem;">
    <div class="stat-card">
        <div class="stat-value" style="color:var(--{{ $diskClass }});">{{ $diskUsed }}%</div>
        <div class="stat-label">Disco usado</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $metrics['pending_jobs'] ?? 0 }}</div>
        <div class="stat-label">Jobs en cola</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--danger);">{{ $metrics['failed_jobs'] ?? 0 }}</div>
        <div class="stat-label">Jobs fallidos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ number_format(($metrics['log_size'] ?? 0) / 1024, 1) }} KB</div>
        <div class="stat-label">Tamaño log Laravel</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $metrics['session_count'] ?? 0 }}</div>
        <div class="stat-label">Sesiones en disco</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--warning);">{{ $metrics['low_stock_count'] ?? 0 }}</div>
        <div class="stat-label">Insumos bajo mínimo</div>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem;border-color:var(--accent);">
    <h3 class="card-title">📥 Importar inventario comercial (CSV)</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
        Carga masiva de productos desde Excel: punto, ubicación, departamento, familia, código, descripción y P.V.P.
    </p>
    <a href="{{ route('programmer.inventory-import') }}" class="btn btn-primary">Abrir importador CSV</a>
    <a href="{{ route('programmer.inventory-import.template') }}" class="btn btn-ghost" style="margin-left:0.5rem;">Plantilla</a>
</div>

<div class="card" style="margin-bottom:1.5rem;border-color:var(--accent);">
    <h3 class="card-title">🖨️ Formatos de impresión térmica</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
        Pre-ticket del mesero, comanda de cocina y ticket de cobro: títulos, campos visibles y pie de página.
    </p>
    <a href="{{ route('programmer.print-templates.index') }}" class="btn btn-primary">Configurar tickets</a>
</div>

<div class="grid grid-3" style="margin-bottom:1.5rem;">
    <form method="POST" action="{{ route('programmer.purge') }}" class="card">
        @csrf
        <h3 class="card-title">🧹 Limpiar cachés</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
            Limpia caché y vistas compiladas. Conserva sesiones y registros de diagnóstico.
        </p>
        <button type="submit" class="btn btn-warning" onclick="return confirm('¿Limpiar las cachés?')">Limpiar cachés</button>
    </form>

    <form method="POST" action="{{ route('programmer.kill-processes') }}" class="card">
        @csrf
        <h3 class="card-title">🛑 Reiniciar trabajadores</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
            Solicita un reinicio ordenado. Conserva impresiones, respaldos pendientes y trabajos fallidos.
        </p>
        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Solicitar el reinicio de los trabajadores?')">Solicitar reinicio</button>
    </form>

    <form method="POST" action="{{ route('programmer.repair-integrity') }}" class="card">
        @csrf
        <h3 class="card-title">🔍 Revisar integridad</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
            Compara el saldo con los movimientos y señala diferencias para revisar documentos.
        </p>
        <button type="submit" class="btn btn-primary">Revisar diferencias</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Historial de ajustes (programmer_adjustment)</h2></div>
    @if($recentAdjustments->isEmpty())
        <p style="color:var(--text-muted);">No hay ajustes registrados.</p>
    @else
    <table class="data-table">
        <thead>
            <tr><th>Fecha</th><th>Insumo</th><th>Cantidad</th><th>Usuario</th><th>Descripción</th></tr>
        </thead>
        <tbody>
            @foreach($recentAdjustments as $log)
            <tr>
                <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $log->supply->name ?? '—' }}</td>
                <td>{{ number_format($log->quantity, 4) }}</td>
                <td>{{ $log->user->name ?? '—' }}</td>
                <td style="font-size:0.8rem;">{{ Str::limit($log->description, 80) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
