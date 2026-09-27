@extends('layouts.app')
@section('title', 'Punto de Venta POS — Cajero')
@push('styles')
<style>
    .table-map { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem; }
    .table-cell {
        aspect-ratio: 1; border-radius: var(--radius); display: flex; flex-direction: column;
        align-items: center; justify-content: center; cursor: pointer; transition: var(--transition);
        border: 2px solid transparent; position: relative; text-decoration: none; color: inherit;
    }
    .table-cell:hover { transform: scale(1.05); }
    .table-cell.free { background: rgba(100,116,139,0.2); border-color: #64748b; color: #e2e8f0; }
    .table-cell.occupied-red { background: #dc2626; border-color: #b91c1c; color: #fff; }
    .table-cell.billing-green { background: #16a34a; border-color: #15803d; color: #fff; animation: pulse-green 2s ease-in-out infinite; }
    .table-number { font-size: 1.5rem; font-weight: 800; color: inherit; text-shadow: 0 1px 2px rgba(0,0,0,0.35); }
    .table-name { font-size: 0.65rem; margin-top: 0.15rem; opacity: 0.95; }
    .table-status { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-top: 0.25rem; letter-spacing: 0.04em; }
    .table-amount { font-size: 0.8rem; font-weight: 800; margin-top: 0.35rem; }
    @keyframes pulse-green { 0%,100%{opacity:1} 50%{opacity:0.85} }
    .legend { display: flex; gap: 1.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); display: inline-block; animation: pulse-danger 2s infinite; }
    .zone-title { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin: 1.25rem 0 0.75rem; }
    .cashier-actions { display:flex; gap:0.5rem; flex-wrap:wrap; justify-content:flex-end; }
    .cash-modal { width: min(680px, 94vw); }
    .cash-summary { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:0.75rem; margin-bottom:1rem; }
    .cash-summary-item { border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-input); padding:0.85rem; }
    .cash-summary-item span { display:block; color:var(--text-muted); font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .cash-summary-item strong { display:block; margin-top:0.3rem; font-size:1rem; }
    .expense-row { display:grid; grid-template-columns: minmax(0, 1fr) 150px 38px; gap:0.5rem; margin-bottom:0.6rem; align-items:center; }
    .icon-btn { width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--bg-input); color:var(--text-secondary); cursor:pointer; }
    .icon-btn:hover { background:var(--bg-card-hover); color:var(--text-primary); }
    .modal-actions { display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.25rem; flex-wrap:wrap; }
    [hidden] { display:none !important; }
    @media (max-width: 720px) {
        .cash-summary { grid-template-columns: 1fr; }
        .expense-row { grid-template-columns: 1fr; }
        .expense-row .icon-btn { width:100%; }
        .page-header { align-items:flex-start; gap:1rem; flex-direction:column; }
        .cashier-actions { justify-content:flex-start; }
    }
</style>
@endpush
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">💳 Punto de Venta</h1>
        <p class="page-subtitle">
            <span class="live-dot"></span> Actualización en vivo —
            <span id="last-update">—</span>
        </p>
    </div>
    <div class="cashier-actions">
        <a href="{{ route('admin.network') }}" class="btn btn-ghost">📡 Tablets LAN</a>
        <a href="{{ route('cashier.delivery.create') }}" class="btn btn-primary">🛵 Nuevo domicilio</a>
        <button type="button" class="btn btn-success" id="open-cash-closure" @disabled($cashierClosureExists)>
            Cierre de caja
        </button>
        @if($cashierClosure)
            <a href="{{ route('cashier.cash-closure.pdf', $cashierClosure) }}" class="btn btn-ghost" target="_blank">
                Ver cierre
            </a>
        @endif
        <form action="{{ route('cashier.report-z') }}" method="POST" onsubmit="return confirm('¿Generar Informe Z? Irreversible.')">
            @csrf
            <button type="submit" class="btn btn-warning">📊 Informe Z</button>
        </form>
    </div>
</div>

<div class="legend">
    <div class="legend-item"><div class="legend-dot" style="background:var(--text-muted);"></div> Libre</div>
    <div class="legend-item"><div class="legend-dot" style="background:#dc2626;"></div> Pendiente / Ocupado</div>
    <div class="legend-item"><div class="legend-dot" style="background:#16a34a;"></div> Por pagar</div>
</div>

<div id="table-map-container">
    @php $zones = collect($tables)->groupBy('zone'); @endphp
    @foreach($zones as $zone => $zoneTables)
    <h3 class="zone-title">{{ $zone }}</h3>
    <div class="table-map" data-zone="{{ $zone }}">
        @foreach($zoneTables as $t)
        <a href="{{ route('cashier.table-detail', $t['number']) }}" class="table-cell {{ $t['color'] }}" data-table="{{ $t['number'] }}">
            <span class="table-number">{{ $t['short_label'] ?? $t['number'] }}</span>
            @if(!empty($t['name']) && $t['name'] !== 'Mesa '.$t['number'])
            <span class="table-name">{{ $t['name'] }}</span>
            @endif
            <span class="table-status">{{ $t['order'] ? $t['order']->status_label : 'Libre' }}</span>
            @if($t['order'] && $t['order']->total > 0)
            <span class="table-amount">{{ cop($t['order']->total) }}</span>
            @endif
        </a>
        @endforeach
    </div>
    @endforeach
</div>

<div class="modal-overlay" id="cash-closure-modal" hidden>
    <div class="modal-content cash-modal">
        <div class="modal-header">
            <div>
                <h2 class="card-title">Cierre de caja diario</h2>
                <p class="page-subtitle">Registra gastos para restarlos de las ventas del dia.</p>
            </div>
            <button type="button" class="modal-close" id="close-cash-closure" aria-label="Cerrar">&times;</button>
        </div>
        <form action="{{ route('cashier.cash-closure') }}" method="POST" id="cash-closure-form">
            @csrf
            <div class="modal-body">
                <div class="cash-summary">
                    <div class="cash-summary-item">
                        <span>Ventas del dia</span>
                        <strong>{{ cop($cashierClosureSummary['total_sales']) }}</strong>
                    </div>
                    <div class="cash-summary-item">
                        <span>Total gastos</span>
                        <strong id="summary-expenses">{{ cop(0) }}</strong>
                    </div>
                    <div class="cash-summary-item">
                        <span>Ventas - gastos</span>
                        <strong id="summary-final">{{ cop($cashierClosureSummary['total_sales']) }}</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Gastos del dia</label>
                    <div id="expenses-list">
                        <div class="expense-row">
                            <input type="text" name="expenses[0][concept]" class="form-input" placeholder="Concepto del gasto">
                            <input type="number" name="expenses[0][amount]" class="form-input expense-amount" placeholder="Valor" min="0" step="0.01">
                            <button type="button" class="icon-btn remove-expense" title="Quitar gasto">x</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" id="add-expense">+ Agregar gasto</button>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" id="cancel-cash-closure">Cancelar</button>
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Generar cierre de caja del dia?')">
                        Generar informe
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
const STATUS_URL = @json(route('api.pos.status'));
const POLL_MS = 4000;
const DAILY_SALES_TOTAL = Number(@json($cashierClosureSummary['total_sales']));

async function refreshTables() {
    try {
        const res = await fetch(STATUS_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) return;
        const data = await res.json();
        document.getElementById('last-update').textContent = new Date(data.updated_at).toLocaleTimeString();

        data.tables.forEach(t => {
            const el = document.querySelector(`[data-table="${t.number}"]`);
            if (!el) return;
            el.className = `table-cell ${t.color}`;
            const n = el.querySelector('.table-number');
            if (n && t.short_label) n.textContent = t.short_label;
            el.querySelector('.table-status').textContent = t.status;
            let amt = el.querySelector('.table-amount');
            if (t.total > 0) {
                if (!amt) { amt = document.createElement('span'); amt.className = 'table-amount'; el.appendChild(amt); }
                amt.textContent = t.total_formatted || new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(t.total);
            } else if (amt) { amt.remove(); }
        });
    } catch (e) { /* servidor offline */ }
}

setInterval(refreshTables, POLL_MS);
refreshTables();

const cashModal = document.getElementById('cash-closure-modal');
const expensesList = document.getElementById('expenses-list');
const openCashClosure = document.getElementById('open-cash-closure');
const closeCashClosure = document.getElementById('close-cash-closure');
const cancelCashClosure = document.getElementById('cancel-cash-closure');
const addExpense = document.getElementById('add-expense');
const summaryExpenses = document.getElementById('summary-expenses');
const summaryFinal = document.getElementById('summary-final');

function formatCop(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value || 0);
}

function recalculateExpenses() {
    const totalExpenses = [...document.querySelectorAll('.expense-amount')]
        .reduce((sum, input) => sum + Number(input.value || 0), 0);

    summaryExpenses.textContent = formatCop(totalExpenses);
    summaryFinal.textContent = formatCop(DAILY_SALES_TOTAL - totalExpenses);
}

function renumberExpenses() {
    [...expensesList.querySelectorAll('.expense-row')].forEach((row, index) => {
        row.querySelectorAll('input').forEach(input => {
            input.name = input.name.replace(/expenses\[\d+\]/, `expenses[${index}]`);
        });
    });
}

function addExpenseRow() {
    const index = expensesList.querySelectorAll('.expense-row').length;
    const row = document.createElement('div');
    row.className = 'expense-row';
    row.innerHTML = `
        <input type="text" name="expenses[${index}][concept]" class="form-input" placeholder="Concepto del gasto">
        <input type="number" name="expenses[${index}][amount]" class="form-input expense-amount" placeholder="Valor" min="0" step="0.01">
        <button type="button" class="icon-btn remove-expense" title="Quitar gasto">x</button>
    `;
    expensesList.appendChild(row);
}

openCashClosure?.addEventListener('click', () => {
    if (openCashClosure.disabled) return;
    cashModal.hidden = false;
});

[closeCashClosure, cancelCashClosure].forEach(button => {
    button?.addEventListener('click', () => cashModal.hidden = true);
});

cashModal?.addEventListener('click', event => {
    if (event.target === cashModal) cashModal.hidden = true;
});

addExpense?.addEventListener('click', addExpenseRow);

expensesList?.addEventListener('input', event => {
    if (event.target.classList.contains('expense-amount')) recalculateExpenses();
});

expensesList?.addEventListener('click', event => {
    if (!event.target.classList.contains('remove-expense')) return;

    const rows = expensesList.querySelectorAll('.expense-row');
    if (rows.length === 1) {
        event.target.closest('.expense-row').querySelectorAll('input').forEach(input => input.value = '');
    } else {
        event.target.closest('.expense-row').remove();
    }

    renumberExpenses();
    recalculateExpenses();
});
</script>
@endpush
