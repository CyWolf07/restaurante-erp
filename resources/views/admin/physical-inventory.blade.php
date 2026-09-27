@extends('layouts.app')
@section('title', 'Conteo Físico')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🔍 Conteo Físico (Ciego)</h1>
        <p class="page-subtitle">Cuenta las existencias físicas, incluidas las reservadas que aún están en bodega. Este registro no ajusta el stock automáticamente.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.store-inventory') }}">
    @csrf
    <div class="card" style="margin-bottom:1rem;">
        <div class="form-group">
            <label class="form-label">Notas del conteo (opcional)</label>
            <textarea name="notes" class="form-textarea" rows="2" placeholder="Turno matutino, auditoría semanal...">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Stock físico contado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($supplies as $supply)
                <tr>
                    <td>
                        <strong>{{ $supply->name }}</strong>
                        <br><small style="color:var(--text-muted);">{{ $supply->unit_label }}</small>
                    </td>
                    <td>
                        <input type="number" step="0.0001" min="0"
                               name="counts[{{ $supply->id }}]"
                               class="form-input"
                               value="{{ old('counts.'.$supply->id) }}"
                               aria-label="Cantidad contada de {{ $supply->name }} en {{ $supply->unit_label }}"
                               inputmode="decimal" max="99999999.9999" placeholder="Sin contar"
                               required>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;text-align:right;">
        <button type="submit" class="btn btn-primary btn-lg"
                onclick="return confirm('¿Procesar conteo y analizar incongruencias?')">
            Analizar incongruencias
        </button>
    </div>
</form>
@endsection
