@extends('layouts.app')
@section('title', 'Inventario de Insumos')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📦 Inventario de Insumos</h1>
        <p class="page-subtitle">Stock actual, compras y mermas</p>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.5rem;">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">Registrar nuevo insumo</h2>
        <form method="POST" action="{{ route('admin.store-supply') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Unidad</label>
                <select name="unit_type" class="form-select" required>
                    <option value="gram">Gramos</option>
                    <option value="milliliter">Mililitros</option>
                    <option value="unit">Unidades</option>
                </select>
            </div>
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label">Stock actual</label>
                    <input type="number" step="0.0001" name="current_stock" class="form-input" value="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock mínimo</label>
                    <input type="number" step="0.0001" name="min_stock" class="form-input" value="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Costo/unidad</label>
                    <input type="number" step="0.01" name="cost_per_unit" class="form-input" value="0" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Proveedor (opcional)</label>
                <input type="text" name="supplier" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">Guardar insumo</button>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Insumo</th>
                <th>Stock</th>
                <th>Mínimo</th>
                <th>Costo</th>
                <th>Compra / Merma</th>
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
                    @if($supply->current_stock <= $supply->min_stock)
                        <span class="badge badge-red">{{ number_format($supply->current_stock, 2) }}</span>
                    @else
                        {{ number_format($supply->current_stock, 2) }}
                    @endif
                </td>
                <td>{{ number_format($supply->min_stock, 2) }}</td>
                <td>${{ number_format($supply->cost_per_unit, 2) }}</td>
                <td>
                    <details>
                        <summary class="btn btn-ghost btn-sm">Acciones</summary>
                        <div style="margin-top:0.75rem;display:flex;flex-direction:column;gap:0.5rem;">
                            <form method="POST" action="{{ route('admin.purchase', $supply) }}">
                                @csrf
                                <input type="number" step="0.0001" name="quantity" class="form-input" placeholder="Cantidad compra" required>
                                <input type="text" name="description" class="form-input" placeholder="Nota" style="margin-top:0.25rem;">
                                <button type="submit" class="btn btn-success btn-sm" style="margin-top:0.25rem;">+ Compra</button>
                            </form>
                            <form method="POST" action="{{ route('admin.waste', $supply) }}">
                                @csrf
                                <input type="number" step="0.0001" name="quantity" class="form-input" placeholder="Cantidad merma" required>
                                <input type="text" name="reason" class="form-input" placeholder="Motivo" style="margin-top:0.25rem;" required>
                                <button type="submit" class="btn btn-danger btn-sm" style="margin-top:0.25rem;">− Merma</button>
                            </form>
                        </div>
                    </details>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
