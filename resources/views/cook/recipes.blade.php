@extends('layouts.app')
@section('title', 'Fichas Técnicas — Cocina')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📖 Fichas Técnicas de Cocina</h1>
        <p class="page-subtitle">Recetas estandarizadas — Solo lectura</p>
    </div>
</div>

@if($categories->isEmpty())
    <div class="card" style="text-align:center;padding:3rem;">
        <p style="font-size:3rem;margin-bottom:1rem;">📭</p>
        <p style="color:var(--text-muted);">No hay platos registrados aún.</p>
    </div>
@endif

@foreach($categories as $category)
@if($category->products->isNotEmpty())
<div style="margin-bottom:2rem;">
    <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
        <span style="width:12px;height:12px;border-radius:50%;background:{{ $category->color }};display:inline-block;"></span>
        {{ $category->name }}
    </h2>
    <div class="grid grid-4">
        @foreach($category->products as $product)
        <div class="card" style="cursor:pointer;padding:0;overflow:hidden;" onclick="openRecipeModal('{{ $product->id }}')">
            <div style="height:160px;background:linear-gradient(135deg,{{ $category->color }}22,{{ $category->color }}44);display:flex;align-items:center;justify-content:center;">
                @if($product->image_path)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:100%;height:100%;object-fit:cover;">
                @else
                    <span style="font-size:3rem;">🍽️</span>
                @endif
            </div>
            <div style="padding:1rem;">
                <h3 style="font-size:0.9rem;font-weight:700;">{{ $product->name }}</h3>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">
                    ⏱️ {{ $product->preparation_time }} min
                </p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endforeach

{{-- Modal de Ficha Técnica --}}
<div id="recipe-modal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeRecipeModal()">
    <div class="modal-content modal-full">
        <div class="modal-header">
            <h2 id="modal-title" style="font-size:1.25rem;font-weight:800;">Ficha Técnica</h2>
            <button class="modal-close" onclick="closeRecipeModal()">✕</button>
        </div>
        <div class="modal-body" id="modal-body">
            <p style="color:var(--text-muted);">Cargando...</p>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
const recipesData = @json($products->load('recipes.supply')->keyBy('id'));

function openRecipeModal(productId) {
    const product = recipesData[productId];
    if (!product) return;

    document.getElementById('modal-title').textContent = '📋 ' + product.name;
    let html = '<div class="grid grid-2" style="gap:2rem;">';

    // Columna izquierda — Ingredientes
    html += '<div>';
    html += '<h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">🥘 Ingredientes Requeridos</h3>';
    html += '<table class="data-table"><thead><tr><th>Insumo</th><th>Cantidad</th><th>Unidad</th></tr></thead><tbody>';
    if (product.recipes && product.recipes.length > 0) {
        product.recipes.forEach(r => {
            const unitLabel = r.supply ? ({gram:'g',milliliter:'ml',unit:'unidad(es)'}[r.supply.unit_type]||r.supply.unit_type) : '';
            html += `<tr><td style="font-weight:600;">${erpEscape(r.supply ? r.supply.name : 'N/A')}</td><td>${parseFloat(r.quantity_required).toFixed(1)}</td><td>${erpEscape(unitLabel)}</td></tr>`;
        });
    } else {
        html += '<tr><td colspan="3" style="color:var(--text-muted);text-align:center;">Sin ingredientes registrados</td></tr>';
    }
    html += '</tbody></table>';
    html += `<div style="margin-top:1.5rem;padding:1rem;background:rgba(99,102,241,0.1);border-radius:var(--radius-sm);border:1px solid rgba(99,102,241,0.2);">`;
    html += `<span style="font-size:0.8rem;color:var(--accent-hover);font-weight:600;">⏱️ Tiempo de preparación: ${product.preparation_time} minutos</span>`;
    html += `</div></div>`;

    // Columna derecha — Instrucciones
    html += '<div>';
    html += '<h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">👨‍🍳 Pasos de Preparación</h3>';
    if (product.recipe_instructions) {
        const steps = product.recipe_instructions.split('\n').filter(s => s.trim());
        html += '<ol style="padding-left:1.25rem;">';
        steps.forEach((step, i) => {
            html += `<li style="margin-bottom:0.75rem;padding:0.75rem;background:var(--bg-input);border-radius:var(--radius-sm);font-size:0.85rem;line-height:1.6;">${erpEscape(step.trim())}</li>`;
        });
        html += '</ol>';
    } else {
        html += '<p style="color:var(--text-muted);font-style:italic;">Sin instrucciones de preparación registradas.</p>';
    }
    if (product.description) {
        html += `<div style="margin-top:1.5rem;padding:1rem;background:var(--bg-input);border-radius:var(--radius-sm);"><strong style="font-size:0.8rem;">Descripción:</strong><p style="font-size:0.85rem;color:var(--text-secondary);margin-top:0.25rem;">${erpEscape(product.description)}</p></div>`;
    }
    html += '</div></div>';

    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('recipe-modal').style.display = 'flex';
}

function closeRecipeModal() {
    document.getElementById('recipe-modal').style.display = 'none';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeRecipeModal(); });
</script>
@endpush
