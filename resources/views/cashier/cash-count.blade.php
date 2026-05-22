@extends('layouts.app')
@section('title', 'Arqueo de caja')
@push('styles')
<style>
    .cash-count-layout { display:grid; grid-template-columns: minmax(0, 1fr) 320px; gap:1.25rem; align-items:start; }
    .cash-tabs { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; }
    .cash-tab {
        border:1px solid var(--border); background:var(--bg-input); color:var(--text-secondary);
        border-radius:var(--radius-sm); padding:0.65rem 1rem; font-weight:700; cursor:pointer;
    }
    .cash-tab.active { background:var(--accent); border-color:var(--accent); color:#fff; }
    .cash-panel { display:none; }
    .cash-panel.active { display:block; }
    .count-row {
        display:grid; grid-template-columns: 1fr 110px 130px; gap:0.75rem; align-items:center;
        padding:0.6rem 0; border-bottom:1px solid rgba(51,65,85,0.7);
    }
    .count-row:last-child { border-bottom:0; }
    .count-denomination { font-weight:800; }
    .line-total { text-align:right; font-weight:800; color:var(--text-secondary); }
    .summary-line { display:flex; justify-content:space-between; gap:1rem; padding:0.55rem 0; border-bottom:1px solid var(--border); }
    .summary-line strong { color:var(--accent-hover); }
    .summary-total { font-size:1.2rem; font-weight:900; padding-top:0.85rem; }
    @media (max-width: 900px) {
        .cash-count-layout { grid-template-columns:1fr; }
        .count-row { grid-template-columns:1fr; }
        .line-total { text-align:left; }
    }
</style>
@endpush
@section('content')
@php
    $sections = [
        'base' => 'Base',
        'change' => 'Moneda de cambio',
        'sales' => 'Venta',
    ];
    $existingCounts = [
        'base' => $cashCount?->base_counts ?? [],
        'change' => $cashCount?->change_counts ?? [],
        'sales' => $cashCount?->sales_counts ?? [],
    ];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Arqueo de caja</h1>
        <p class="page-subtitle">Conteo de base, cambio y venta para anexar al cierre impreso.</p>
    </div>
    <a href="{{ route('cashier.pos') }}" class="btn btn-ghost">Volver al POS</a>
</div>

<form action="{{ route('cashier.cash-count.store') }}" method="POST">
    @csrf
    <div class="cash-count-layout">
        <div class="card">
            <div class="cash-tabs" role="tablist">
                @foreach($sections as $key => $label)
                    <button type="button" class="cash-tab {{ $loop->first ? 'active' : '' }}" data-tab="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>

            @foreach($sections as $key => $label)
            <section class="cash-panel {{ $loop->first ? 'active' : '' }}" id="panel-{{ $key }}">
                <h2 class="card-title" style="margin-bottom:0.75rem;">{{ $label }}</h2>
                @foreach($denominations as $denomination)
                @php $qty = (int) ($existingCounts[$key][(string) $denomination] ?? 0); @endphp
                <div class="count-row">
                    <div class="count-denomination">{{ cop($denomination) }}</div>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        class="form-input cash-count-input"
                        name="{{ $key }}[{{ $denomination }}]"
                        value="{{ $qty }}"
                        data-section="{{ $key }}"
                        data-denomination="{{ $denomination }}"
                    >
                    <div class="line-total" data-line-total="{{ $key }}-{{ $denomination }}">{{ cop($denomination * $qty) }}</div>
                </div>
                @endforeach
            </section>
            @endforeach

            <div class="form-group" style="margin-top:1rem;">
                <label class="form-label">Notas del arqueo</label>
                <textarea name="notes" class="form-textarea" rows="3" placeholder="Observaciones, sobrantes o faltantes...">{{ old('notes', $cashCount?->notes) }}</textarea>
            </div>
        </div>

        <aside class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Resumen</h2>
            <div class="summary-line"><span>Base</span><strong id="base-total">{{ cop($cashCount?->base_total ?? 0) }}</strong></div>
            <div class="summary-line"><span>Moneda de cambio</span><strong id="change-total">{{ cop($cashCount?->change_total ?? 0) }}</strong></div>
            <div class="summary-line"><span>Venta</span><strong id="sales-total">{{ cop($cashCount?->sales_total ?? 0) }}</strong></div>
            <div class="summary-line summary-total"><span>Total contado</span><strong id="declared-total">{{ cop($cashCount?->declared_cash_total ?? 0) }}</strong></div>
            <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;margin-top:1rem;">Guardar arqueo</button>
        </aside>
    </div>
</form>
@endsection
@push('scripts')
<script>
function formatCop(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value || 0);
}

function recalculateCashCount() {
    const totals = { base: 0, change: 0, sales: 0 };

    document.querySelectorAll('.cash-count-input').forEach(input => {
        const section = input.dataset.section;
        const denomination = Number(input.dataset.denomination);
        const quantity = Number(input.value || 0);
        const lineTotal = denomination * quantity;
        totals[section] += lineTotal;

        const line = document.querySelector(`[data-line-total="${section}-${denomination}"]`);
        if (line) line.textContent = formatCop(lineTotal);
    });

    document.getElementById('base-total').textContent = formatCop(totals.base);
    document.getElementById('change-total').textContent = formatCop(totals.change);
    document.getElementById('sales-total').textContent = formatCop(totals.sales);
    document.getElementById('declared-total').textContent = formatCop(totals.base + totals.change + totals.sales);
}

document.querySelectorAll('.cash-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.cash-tab').forEach(item => item.classList.remove('active'));
        document.querySelectorAll('.cash-panel').forEach(panel => panel.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(`panel-${tab.dataset.tab}`).classList.add('active');
    });
});

document.querySelectorAll('.cash-count-input').forEach(input => {
    input.addEventListener('input', recalculateCashCount);
});

recalculateCashCount();
</script>
@endpush
