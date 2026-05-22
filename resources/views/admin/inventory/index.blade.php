@extends('layouts.app')
@section('title', 'Inventario Comercial')
@push('styles')
<style>
    .inv-tabs { display:flex; gap:0.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .inv-tab { padding:0.6rem 1.25rem; border-radius:999px; border:1px solid var(--border); background:transparent; color:var(--text-secondary); font-weight:600; font-size:0.85rem; text-decoration:none; }
    .inv-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }
    .inv-toolbar { display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1rem; align-items:flex-end; }
    .inv-edit-panel {
        margin-top:0.75rem; padding:1rem; background:var(--bg-input);
        border-radius:var(--radius-sm); border:1px solid var(--border);
    }
    .data-table details summary { cursor:pointer; list-style:none; }
    .data-table details summary::-webkit-details-marker { display:none; }
    .inv-row-actions { display:inline-flex; gap:0.35rem; align-items:center; flex-wrap:wrap; }
    .inv-table-wrap { overflow-x:auto; margin:0 -0.25rem; }
    .inv-table-wrap .data-table { font-size:0.72rem; min-width:1400px; }
    .inv-table-wrap .data-table th { white-space:nowrap; font-size:0.68rem; }
    .inv-table-wrap .data-table td { white-space:nowrap; }
    .inv-num { text-align:right; font-variant-numeric:tabular-nums; }
    .inv-diff-warn { color:var(--warning); font-weight:700; }
    .inv-diff-ok { color:var(--text-muted); }
</style>
@endpush
@section('content')
<section class="page-header">
    <section>
        <h1 class="page-title">📦 Inventario Comercial</h1>
        <p class="page-subtitle">Control manual tipo Excel — ingresos (Compras), salidas (Ventas), valores en COP</p>
    </section>
</section>

<section class="inv-tabs">
    <a href="{{ route('admin.inventory.index', ['tab' => 'inventario']) }}" class="inv-tab {{ $tab === 'inventario' ? 'active' : '' }}">Ver inventario</a>
    <a href="{{ route('admin.inventory.index', ['tab' => 'registro']) }}" class="inv-tab {{ $tab === 'registro' ? 'active' : '' }}">+ Nuevo producto</a>
    <a href="{{ route('admin.inventory.index', ['tab' => 'compras']) }}" class="inv-tab {{ $tab === 'compras' ? 'active' : '' }}">Compras (ingresos)</a>
</section>

@if($tab === 'inventario')
<section class="card">
    <form method="GET" class="inv-toolbar">
        <input type="hidden" name="tab" value="inventario">
        <section class="form-group" style="margin:0;">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-input" value="{{ request('q') }}" placeholder="Código o artículo">
        </section>
        <section class="form-group" style="margin:0;">
            <label class="form-label">Punto</label>
            <select name="point" class="form-select">
                <option value="">Todos</option>
                @foreach($points as $k => $label)
                <option value="{{ $k }}" @selected(request('point')==$k)>{{ $label }}</option>
                @endforeach
            </select>
        </section>
        <section class="form-group" style="margin:0;">
            <label class="form-label">Familia</label>
            <select name="family" class="form-select">
                <option value="">Todas</option>
                @foreach($families as $k => $label)
                <option value="{{ $k }}" @selected(request('family')==$k)>{{ $label }}</option>
                @endforeach
            </select>
        </section>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>

    <div class="inv-table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Restaurante</th><th>Ubicación</th><th>Depto</th><th>Familia</th>
                <th>Código</th><th>Artículo</th>
                <th class="inv-num" title="Suma Compras (ingresos)">Entrada</th>
                <th class="inv-num" title="Suma V total compras">V/Entrada</th>
                <th class="inv-num" title="Suma ventas (consumo)">Salida</th>
                <th class="inv-num" title="(V/Entrada÷Entrada)×Salida">V/Salida</th>
                <th class="inv-num" title="Entrada − Salida">Stock</th>
                <th class="inv-num" title="V/Entrada − V/Salida">V/Stock</th>
                <th class="inv-num" title="P.V.P">Precio</th>
                <th class="inv-num" title="V/Stock ÷ Stock">V.C.U.</th>
                <th class="inv-num" title="Conteo físico manual">Inventario</th>
                <th class="inv-num" title="Stock − Inventario">Diferencia</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($supplies as $s)
            @php $m = $s->commerce_metrics ?? []; @endphp
            <tr>
                <td>{{ $s->point_label }}</td>
                <td>{{ $s->location ?? '—' }}</td>
                <td>{{ $s->department_number ?? '—' }}</td>
                <td>{{ $s->family_label }}</td>
                <td><strong>{{ $s->code }}</strong></td>
                <td style="font-weight:600;">{{ $s->name }}</td>
                <td class="inv-num">{{ number_format($m['entrada'] ?? 0, 3, ',', '.') }}</td>
                <td class="inv-num">{{ cop($m['v_entrada'] ?? 0) }}</td>
                <td class="inv-num">{{ number_format($m['salida'] ?? 0, 3, ',', '.') }}</td>
                <td class="inv-num">{{ cop($m['v_salida'] ?? 0) }}</td>
                <td class="inv-num">{{ number_format($m['stock'] ?? 0, 3, ',', '.') }}</td>
                <td class="inv-num">{{ cop($m['v_stock'] ?? 0) }}</td>
                <td class="inv-num">{{ cop($m['precio'] ?? 0) }}</td>
                <td class="inv-num">{{ cop($m['vcu'] ?? 0) }}</td>
                <td class="inv-num">{{ number_format($m['inventario'] ?? 0, 3, ',', '.') }}</td>
                <td class="inv-num @if(abs($m['diferencia'] ?? 0) > 0.001) inv-diff-warn @else inv-diff-ok @endif">
                    {{ number_format($m['diferencia'] ?? 0, 3, ',', '.') }}
                </td>
                <td style="white-space:nowrap;">
                    <div class="inv-row-actions">
                        <details @if(request('edit') === $s->id || session('open_edit') === $s->id) open @endif>
                            <summary class="btn btn-ghost btn-sm">✏️ Editar</summary>
                            <div class="inv-edit-panel">
                                @include('admin.inventory.partials.edit-form', ['supply' => $s])
                            </div>
                        </details>
                        <form method="POST" action="{{ route('admin.inventory.destroy', $s) }}"
                              onsubmit="return confirm('¿Eliminar el producto {{ $s->code }}?\n\nSi tiene compras o movimientos solo se desactivará.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="16" style="text-align:center;color:var(--text-muted);">Sin productos. Usa «Nuevo producto».</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    <p style="font-size:0.7rem;color:var(--text-muted);margin-top:0.75rem;">
        Entrada y V/Entrada desde <strong>Compras (ingresos)</strong>. Salida desde ventas cobradas (consumo en recetas).
        Inventario físico se edita en ✏️. Stock operativo del sistema: {{-- opcional --}} ver campo «Stock actual» al editar.
    </p>
    <section style="margin-top:1rem;">{{ $supplies->links() }}</section>
</section>
@endif

@if($tab === 'registro')
<section class="card">
    <form method="POST" action="{{ route('admin.inventory.store') }}">
        @csrf
        <section class="grid grid-3">
            <section class="form-group">
                <label class="form-label">Punto *</label>
                <select name="point" class="form-select" required>
                    @foreach($points as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </section>
            <section class="form-group">
                <label class="form-label">Ubicación</label>
                <input type="text" name="location" class="form-input">
            </section>
            <section class="form-group">
                <label class="form-label">No. Departamento</label>
                <select name="department_number" class="form-select">
                    <option value="">— Sin asignar —</option>
                    @foreach($departments as $num => $deptLabel)
                    <option value="{{ $num }}">{{ $num }} — {{ $deptLabel }}</option>
                    @endforeach
                </select>
            </section>
            <section class="form-group">
                <label class="form-label">Familia *</label>
                <select name="family" class="form-select" required>
                    @foreach($families as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </section>
            <section class="form-group">
                <label class="form-label">Código *</label>
                <input type="text" name="code" class="form-input" required>
            </section>
            <section class="form-group">
                <label class="form-label">P.V.P (COP) *</label>
                <input type="number" step="1" name="pvp" class="form-input" required>
            </section>
        </section>
        <section class="form-group">
            <label class="form-label">Nombre corto *</label>
            <input type="text" name="name" class="form-input" required>
        </section>
        <section class="grid grid-3">
            <section class="form-group">
                <label class="form-label">Unidad</label>
                <select name="unit_type" class="form-select">
                    <option value="unit">Unidad</option>
                    <option value="gram">Gramos</option>
                    <option value="milliliter">Mililitros</option>
                </select>
            </section>
            <section class="form-group">
                <label class="form-label">Stock inicial</label>
                <input type="number" step="0.0001" name="current_stock" class="form-input" value="0">
            </section>
            <section class="form-group">
                <label class="form-label">Costo unitario (COP)</label>
                <input type="number" step="1" name="cost_per_unit" class="form-input" value="0">
            </section>
        </section>
        <button type="submit" class="btn btn-primary">Registrar producto</button>
    </form>
</section>
@endif

@if($tab === 'compras')
<section class="grid grid-2">
    <section class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">Registrar compra / ingreso</h2>
        <form method="POST" action="{{ route('admin.inventory.purchase') }}">
            @csrf
            <section class="form-group">
                <label class="form-label">Código producto *</label>
                <input type="text" name="code" class="form-input" list="supply-codes" required>
                <datalist id="supply-codes">
                    @foreach(\App\Models\Supply::whereNotNull('code')->pluck('code') as $c)
                    <option value="{{ $c }}">
                    @endforeach
                </datalist>
            </section>
            <section class="form-group">
                <label class="form-label">Proveedor *</label>
                <input type="text" name="supplier" class="form-input" required>
            </section>
            <section class="grid grid-2">
                <section class="form-group">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="purchase_date" class="form-input" value="{{ date('Y-m-d') }}" required>
                </section>
                <section class="form-group">
                    <label class="form-label">Tipo ajuste *</label>
                    <select name="adjustment_type" class="form-select" required>
                        @foreach($adjustmentTypes as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </section>
            </section>
            <section class="grid grid-2">
                <section class="form-group">
                    <label class="form-label">Cantidad *</label>
                    <input type="number" step="0.0001" name="quantity" class="form-input" required>
                </section>
                <section class="form-group">
                    <label class="form-label">Valor/unit (COP) *</label>
                    <input type="number" step="1" name="unit_value" class="form-input" required>
                </section>
            </section>
            <section class="grid grid-2">
                <section class="form-group">
                    <label class="form-label">No. Factura</label>
                    <input type="text" name="invoice_number" class="form-input">
                </section>
                <section class="form-group">
                    <label class="form-label">Punto *</label>
                    <select name="point" class="form-select" required>
                        @foreach($points as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </section>
            </section>
            <button type="submit" class="btn btn-success">Registrar compra</button>
        </form>
    </section>
    <section class="card">
        <h2 class="card-title">Últimas compras</h2>
        <table class="data-table" style="margin-top:1rem;font-size:0.8rem;">
            <thead><tr><th>Fecha</th><th>Cód.</th><th>Tipo</th><th>Cantidad</th><th>V total</th></tr></thead>
            <tbody>
                @foreach($purchases as $p)
                <tr>
                    <td>{{ $p->purchase_date->format('d/m/Y') }}</td>
                    <td>{{ $p->code }}</td>
                    <td>{{ $p->adjustment_type_label }}</td>
                    <td>{{ number_format($p->quantity, 3, ',', '.') }}</td>
                    <td>{{ cop($p->total_value) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</section>
@endif
@endsection
