@extends('layouts.app')
@section('title', 'Opciones de Platos')
@push('styles')
<style>
    .mod-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    @media(max-width:900px){ .mod-grid { grid-template-columns:1fr; } }

    .option-chip {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;
        border: 1px solid var(--border); background: var(--bg-input);
        cursor: pointer; transition: var(--transition); user-select: none;
    }
    .option-chip.active   { background: rgba(99,102,241,0.2); border-color: var(--accent); color: var(--accent-hover); }
    .option-chip.disabled { opacity: 0.4; }
    .option-chip:hover    { border-color: var(--accent-hover); }

    .group-label {
        font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.08em; color: var(--text-muted); margin: 0.75rem 0 0.35rem;
    }
    .chip-wrap { display: flex; flex-wrap: wrap; gap: 0.4rem; }

    .accordion-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.75rem 1rem; cursor: pointer; user-select: none;
        border-radius: var(--radius-sm); transition: var(--transition);
    }
    .accordion-header:hover { background: var(--bg-card-hover); }
    .accordion-body { padding: 0 1rem 1rem; }

    .mod-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid var(--border); }
    .mod-row:last-child { border-bottom: none; }
    .badge-type { padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
    .badge-option { background: rgba(34,197,94,0.15); color: var(--success); }
    .badge-addon  { background: rgba(245,158,11,0.15); color: var(--warning); }

    .product-override-row { padding: 0.5rem 0; border-bottom: 1px solid var(--border); display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; }
    .product-override-row:last-child { border-bottom:none; }
</style>
@endpush
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🧩 Opciones de Platos</h1>
        <p class="page-subtitle">Gestiona opciones de preparación por categoría y por plato individual</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1.5rem;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:1.5rem;">❌ {{ session('error') }}</div>
@endif

<div class="mod-grid">

    {{-- ══ COLUMNA IZQUIERDA: Lista de opciones/modificadores ══ --}}
    <div>
        {{-- Crear nueva opción --}}
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <span class="card-title">➕ Crear nueva opción</span>
            </div>
            <form action="{{ route('admin.modifiers.store') }}" method="POST">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Nombre *</label>
                        <input name="name" class="form-input" required placeholder="Ej: Sin ensalada" maxlength="120">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Tipo *</label>
                        <select name="type" class="form-select" id="new-type-select">
                            <option value="option">Opción cocina (sin costo)</option>
                            <option value="addon">Adicional con precio</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;" id="new-group-field">
                        <label class="form-label">Grupo visual</label>
                        <input name="group" class="form-input" placeholder="Ej: Sin, Sopa, Extras" maxlength="80">
                    </div>
                    <div class="form-group" style="margin-bottom:0;" id="new-price-field" hidden>
                        <label class="form-label">Precio adicional</label>
                        <input name="price" type="number" class="form-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Orden</label>
                        <input name="sort_order" type="number" class="form-input" min="0" value="0">
                    </div>
                </div>
                <div style="margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Guardar opción</button>
                </div>
            </form>
        </div>

        {{-- Tabla de todas las opciones --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">📋 Todas las opciones ({{ $modifiers->count() }})</span>
            </div>
            @forelse($modifiers->groupBy('type') as $type => $group)
            <div class="group-label">{{ $type === 'option' ? '🍽️ Opciones de cocina' : '💰 Adicionales con precio' }}</div>
            @foreach($group as $mod)
            <div class="mod-row">
                <span class="badge-type {{ $mod->type === 'option' ? 'badge-option' : 'badge-addon' }}">
                    {{ $mod->type === 'option' ? 'Opción' : 'Addon' }}
                </span>
                @if($mod->group)
                <span style="font-size:0.7rem;color:var(--text-muted);min-width:80px;">{{ $mod->group }}</span>
                @endif
                <span style="flex:1;font-size:0.85rem;font-weight:600;">{{ $mod->name }}</span>
                @if($mod->type === 'addon')
                <span style="font-size:0.8rem;color:var(--accent-hover);">{{ cop($mod->price) }}</span>
                @endif
                <div style="display:flex;gap:0.4rem;align-items:center;">
                    {{-- Toggle activo --}}
                    <form action="{{ route('admin.modifiers.toggle', $mod) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-sm {{ $mod->active ? 'btn-success' : 'btn-ghost' }}"
                            title="{{ $mod->active ? 'Deshabilitar' : 'Habilitar' }}" style="padding:0.25rem 0.6rem;font-size:0.7rem;">
                            {{ $mod->active ? '✓ Activo' : '✗ Inactivo' }}
                        </button>
                    </form>
                    {{-- Editar --}}
                    <button class="btn btn-ghost btn-sm" style="padding:0.25rem 0.6rem;font-size:0.7rem;"
                        onclick="openEditModal('{{ $mod->id }}','{{ addslashes($mod->name) }}','{{ $mod->group }}','{{ $mod->sort_order }}','{{ $mod->price }}')">
                        ✏️
                    </button>
                    {{-- Eliminar --}}
                    <form action="{{ route('admin.modifiers.destroy', $mod) }}" method="POST"
                        onsubmit="return confirm('¿Eliminar «{{ $mod->name }}»? Se quitará de todos los platos.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.6rem;font-size:0.7rem;">🗑</button>
                    </form>
                </div>
            </div>
            @endforeach
            @empty
            <p style="color:var(--text-muted);text-align:center;padding:1.5rem 0;">No hay opciones creadas aún.</p>
            @endforelse
        </div>
    </div>

    {{-- ══ COLUMNA DERECHA: Asignación por categoría y plato ══ --}}
    <div>
        {{-- Opciones por categoría --}}
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <span class="card-title">🏷️ Opciones por Categoría</span>
            </div>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">
                Marca las opciones que aplican para cada categoría. Los platos sin override propio las heredarán automáticamente.
            </p>
            @foreach($categories as $cat)
            @php $catOptions = $cat->modifiers->where('type','option'); @endphp
            <div style="border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:0.75rem;overflow:hidden;">
                <div class="accordion-header" onclick="toggleAccordion('cat-{{ $cat->id }}')">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $cat->color ?? '#6366f1' }};display:inline-block;"></span>
                        <strong style="font-size:0.9rem;">{{ $cat->name }}</strong>
                        <span style="font-size:0.75rem;color:var(--text-muted);">{{ $catOptions->count() }} opciones</span>
                    </div>
                    <span style="color:var(--text-muted);">▾</span>
                </div>
                <div class="accordion-body" id="cat-{{ $cat->id }}" style="display:none;">
                    <form action="{{ route('admin.modifiers.sync-category', $cat) }}" method="POST">
                        @csrf
                        @php $allOptions = $modifiers->where('type','option')->where('active',true); @endphp
                        @foreach($allOptions->groupBy('group') as $grp => $opts)
                        <div class="group-label">{{ $grp ?? 'General' }}</div>
                        <div class="chip-wrap">
                            @foreach($opts as $opt)
                            @php
                                $assigned = $catOptions->firstWhere('id', $opt->id);
                                $isOn = $assigned && $assigned->pivot->enabled;
                            @endphp
                            <label class="option-chip {{ $isOn ? 'active' : '' }}" title="{{ $opt->name }}">
                                <input type="checkbox" name="modifiers[]" value="{{ $opt->id }}"
                                    {{ $isOn ? 'checked' : '' }} style="display:none;"
                                    onchange="this.closest('label').classList.toggle('active', this.checked)">
                                {{ $opt->name }}
                            </label>
                            @endforeach
                        </div>
                        @endforeach
                        <div style="margin-top:1rem;">
                            <button type="submit" class="btn btn-primary btn-sm">Guardar opciones de {{ $cat->name }}</button>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Override por plato --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">🍴 Override por Plato Individual</span>
            </div>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">
                Si un plato tiene opciones propias asignadas, <strong>ignora</strong> las de su categoría. Útil para Especialidades con preparaciones únicas.
            </p>
            @foreach($categories as $cat)
            @php $catProds = $products->where('category_id', $cat->id); @endphp
            @if($catProds->isNotEmpty())
            <div style="border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:0.75rem;overflow:hidden;">
                <div class="accordion-header" onclick="toggleAccordion('pcat-{{ $cat->id }}')">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $cat->color ?? '#6366f1' }};display:inline-block;"></span>
                        <strong style="font-size:0.9rem;">{{ $cat->name }}</strong>
                        <span style="font-size:0.75rem;color:var(--text-muted);">{{ $catProds->count() }} platos</span>
                    </div>
                    <span style="color:var(--text-muted);">▾</span>
                </div>
                <div class="accordion-body" id="pcat-{{ $cat->id }}" style="display:none;">
                    @foreach($catProds as $prod)
                    <div class="product-override-row">
                        <span style="flex:1;font-size:0.85rem;font-weight:600;">{{ $prod->name }}</span>
                        @if($prod->uses_product_modifiers)
                            <span style="font-size:0.7rem;color:var(--success);">✓ Override activo ({{ $prod->modifiers->count() }} opciones)</span>
                            <button class="btn btn-ghost btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem;"
                                onclick="openProductModal('{{ $prod->id }}','{{ addslashes($prod->name) }}')">Editar</button>
                            <form action="{{ route('admin.modifiers.clear-product', $prod) }}" method="POST"
                                onsubmit="return confirm('¿Quitar override? El plato heredará opciones de su categoría.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem;">Quitar</button>
                            </form>
                        @else
                            <span style="font-size:0.7rem;color:var(--text-muted);">Hereda de categoría</span>
                            <button class="btn btn-ghost btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem;"
                                onclick="openProductModal('{{ $prod->id }}','{{ addslashes($prod->name) }}')">Personalizar</button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>

{{-- Modal editar modificador --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:200;align-items:center;justify-content:center;">
    <div class="card" style="width:420px;max-width:95vw;">
        <h3 style="font-weight:700;margin-bottom:1rem;">✏️ Editar opción</h3>
        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="form-group"><label class="form-label">Nombre</label>
                <input name="name" id="edit-name" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Grupo visual</label>
                <input name="group" id="edit-group" class="form-input" placeholder="Sopa, Sin, Extras…"></div>
            <div class="form-group"><label class="form-label">Orden</label>
                <input name="sort_order" id="edit-sort" type="number" class="form-input" min="0"></div>
            <div class="form-group"><label class="form-label">Precio adicional</label>
                <input name="price" id="edit-price" type="number" class="form-input" min="0" step="0.01"></div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal override por plato --}}
<div id="product-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:200;align-items:center;justify-content:center;">
    <div class="card" style="width:520px;max-width:95vw;max-height:85vh;overflow-y:auto;">
        <h3 style="font-weight:700;margin-bottom:0.5rem;">🍴 Opciones de <span id="prod-modal-name"></span></h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Selecciona las opciones exclusivas para este plato.</p>
        <form id="product-form" method="POST">
            @csrf
            @php $allOptions = $modifiers->where('type','option')->where('active',true); @endphp
            @foreach($allOptions->groupBy('group') as $grp => $opts)
            <div class="group-label">{{ $grp ?? 'General' }}</div>
            <div class="chip-wrap" style="margin-bottom:0.5rem;">
                @foreach($opts as $opt)
                <label class="option-chip prod-option-chip" data-id="{{ $opt->id }}" title="{{ $opt->name }}">
                    <input type="checkbox" name="modifiers[]" value="{{ $opt->id }}" style="display:none;"
                        onchange="this.closest('label').classList.toggle('active', this.checked)">
                    {{ $opt->name }}
                </label>
                @endforeach
            </div>
            @endforeach
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.25rem;">
                <button type="button" class="btn btn-ghost" onclick="closeProductModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar opciones del plato</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
// Productos con sus modificadores (para precargar modal)
const productsData = @json($products->mapWithKeys(fn($p) => [$p->id => $p->modifiers->pluck('id')]));

function toggleAccordion(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// Modal editar modificador
function openEditModal(id, name, group, sort, price) {
    document.getElementById('edit-form').action = `/admin/modifiers/${id}`;
    document.getElementById('edit-name').value  = name;
    document.getElementById('edit-group').value = group || '';
    document.getElementById('edit-sort').value  = sort;
    document.getElementById('edit-price').value = price;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('edit-modal').style.display = 'none'; }
document.getElementById('edit-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeEditModal(); });

// Modal override por plato
function openProductModal(productId, productName) {
    document.getElementById('product-form').action = `/admin/modifiers/sync-product/${productId}`;
    document.getElementById('prod-modal-name').textContent = productName;
    const assigned = productsData[productId] || [];
    document.querySelectorAll('.prod-option-chip').forEach(chip => {
        const id = chip.dataset.id;
        const cb = chip.querySelector('input');
        const isOn = assigned.includes(id);
        cb.checked = isOn;
        chip.classList.toggle('active', isOn);
    });
    document.getElementById('product-modal').style.display = 'flex';
}
function closeProductModal() { document.getElementById('product-modal').style.display = 'none'; }
document.getElementById('product-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeProductModal(); });

// Mostrar/ocultar precio según tipo en formulario nuevo
document.getElementById('new-type-select').addEventListener('change', function() {
    document.getElementById('new-price-field').hidden  = this.value !== 'addon';
    document.getElementById('new-group-field').hidden  = false;
});
</script>
@endpush
