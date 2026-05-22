@extends('layouts.app')
@section('title', 'Dashboard - Control')

@push('styles')
<style>
.dash-actions { display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; }
.kpi-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:1rem; margin-bottom:1.25rem; }
.kpi-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:1rem; min-width:0; }
.kpi-value { font-size:1.45rem; font-weight:800; line-height:1.1; overflow-wrap:anywhere; }
.kpi-label { color:var(--text-muted); font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; margin-top:0.35rem; }
.chart-card { min-height:340px; }
.chart-box { position:relative; height:280px; }
.chart-box.tall { height:340px; }
.close-form { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; }
.close-form .form-input { width:6rem; }
.table-scroll { max-height:360px; overflow:auto; border:1px solid rgba(51,65,85,0.65); border-radius:var(--radius-sm); }
.table-scroll .data-table thead th { position:sticky; top:0; z-index:1; }
.rank-number { color:var(--accent-hover); font-weight:800; }
.muted-note { color:var(--text-muted); font-size:0.78rem; margin-top:0.35rem; }
.compare-form { display:grid; grid-template-columns:1fr 1fr auto; gap:0.75rem; align-items:end; }
.compare-result { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; margin-top:1rem; }
.compare-panel { border:1px solid var(--border); border-radius:var(--radius-sm); padding:1rem; background:rgba(15,23,42,0.38); }
.delta { font-size:0.8rem; font-weight:700; }
.delta.up { color:var(--success); }
.delta.down { color:var(--danger); }
.print-only-title { display:none; }
@media (max-width:1200px) { .kpi-grid { grid-template-columns:repeat(3, 1fr); } }
@media (max-width:768px) { .kpi-grid, .compare-result, .compare-form { grid-template-columns:1fr; } }
@media print {
    .sidebar, .dash-actions, .close-form, .compare-form, .btn, .alert, .no-print { display:none !important; }
    .main-content { margin-left:0 !important; padding:0 !important; }
    body { background:#fff !important; color:#111827 !important; }
    .print-only-title { display:block; margin-bottom:0.5rem; color:#111827; }
    .card, .kpi-card, .stat-card { background:#fff !important; color:#111827 !important; border:1px solid #d1d5db !important; box-shadow:none !important; break-inside:avoid; }
    .grid, .grid-2, .grid-3, .grid-4, .kpi-grid { display:grid !important; grid-template-columns:repeat(2, 1fr) !important; gap:0.75rem !important; }
    .chart-box { height:230px !important; }
    .table-scroll { max-height:none; overflow:visible; }
    .data-table thead th, .data-table tbody td, .kpi-label, .muted-note { color:#111827 !important; }
}
</style>
@endpush

@section('content')
@php
    $monthlySnapshot = $monthly['snapshot'] ?? [];
    $monthlyTotals = $monthlySnapshot['totals'] ?? [];
    $reportsForCompare = $monthly['reports_for_compare'] ?? collect();
    $maxDayWeekSales = max(1, (float) collect($monthlySnapshot['day_of_week_ranking'] ?? [])->max('sales'));
    $profitColor = ($monthlyTotals['gross_profit'] ?? 0) >= 0 ? 'var(--success)' : 'var(--danger)';
@endphp

<h1 class="print-only-title">Dashboard mensual - {{ $monthlySnapshot['period']['label'] ?? now()->format('m/Y') }}</h1>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard de Control</h1>
        <p class="page-subtitle">Ganancias, rankings completos, graficas y comparacion historica</p>
    </div>
    <div class="dash-actions">
        <button type="button" class="btn btn-ghost" onclick="window.print()">Imprimir Dashboard</button>
        <a href="{{ route('admin.physical-inventory') }}" class="btn btn-primary">Nuevo Conteo Fisico</a>
    </div>
</div>

@if(count($lowStockSupplies) > 0)
<div class="alert alert-warning">{{ count($lowStockSupplies) }} insumo(s) por debajo del stock minimo.</div>
@endif

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-value" style="color:{{ $profitColor }}">${{ number_format($monthlyTotals['gross_profit'] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">Ganancia bruta</div></div>
    <div class="kpi-card"><div class="kpi-value">${{ number_format($monthlyTotals['sales'] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">Ventas menu</div></div>
    <div class="kpi-card"><div class="kpi-value" style="color:var(--warning);">${{ number_format($monthlyTotals['ingredient_cost'] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">Costo insumos</div></div>
    <div class="kpi-card"><div class="kpi-value">{{ number_format($monthlyTotals['profit_margin'] ?? 0, 2, ',', '.') }}%</div><div class="kpi-label">Margen bruto</div></div>
    <div class="kpi-card"><div class="kpi-value">{{ number_format($monthlyTotals['orders_count'] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">Ordenes pagadas</div></div>
    <div class="kpi-card"><div class="kpi-value">{{ number_format($monthlyTotals['products_sold'] ?? 0, 0, ',', '.') }}</div><div class="kpi-label">Platos vendidos</div></div>
</div>

<div class="grid grid-3" style="margin-bottom:1.25rem;">
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Desglose de ganancia</h2></div>
        <div class="chart-box"><canvas id="profitChart"></canvas></div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Top 10 platos</h2></div>
        <div class="chart-box"><canvas id="productsChart"></canvas></div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Top 10 insumos</h2></div>
        <div class="chart-box"><canvas id="suppliesChart"></canvas></div>
    </div>
</div>

<div class="grid grid-3" style="margin-bottom:1.25rem;">
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Ventas por mesa</h2></div>
        <div class="chart-box"><canvas id="tablesChart"></canvas></div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Ventas por dia de semana</h2></div>
        <div class="chart-box"><canvas id="weekChart"></canvas></div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Tendencia diaria</h2></div>
        <div class="chart-box"><canvas id="dailyChart"></canvas></div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Cierre mensual</h2></div>
        <p class="muted-note">El cierre guarda el snapshot comprimido dentro del programa y genera un PDF imprimible.</p>
        <div style="margin-top:1rem;">
            @if(!empty($monthly['closed_report']))
                <a href="{{ route('admin.monthly-reports.pdf', $monthly['closed_report']) }}" class="btn btn-success" target="_blank">Imprimir cierre actual</a>
            @else
                <form method="POST" action="{{ route('admin.monthly-reports.close') }}" class="close-form" onsubmit="return confirm('Cerrar este mes y generar el informe imprimible?');">
                    @csrf
                    <input type="number" name="year" class="form-input" value="{{ $monthly['period']['year'] ?? now()->year }}" min="2020" max="2100" title="Ano">
                    <input type="number" name="month" class="form-input" value="{{ $monthly['period']['month'] ?? now()->month }}" min="1" max="12" title="Mes">
                    <button type="submit" class="btn btn-warning">Cerrar mes e imprimir</button>
                </form>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Cierres guardados</h2></div>
        <div class="table-scroll" style="max-height:220px;">
            <table class="data-table">
                <thead><tr><th>Periodo</th><th>Ganancia</th><th>Cerrado por</th><th></th></tr></thead>
                <tbody>
                    @forelse($monthly['recent_reports'] ?? [] as $report)
                    <tr>
                        <td>{{ str_pad($report->period_month, 2, '0', STR_PAD_LEFT) }}/{{ $report->period_year }}</td>
                        <td>${{ number_format((float) $report->gross_profit, 0, ',', '.') }}</td>
                        <td>{{ $report->closer->name ?? '-' }}</td>
                        <td><a href="{{ route('admin.monthly-reports.pdf', $report) }}" class="btn btn-ghost btn-sm" target="_blank">Imprimir</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No hay cierres mensuales aun.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Comparacion historica</h2></div>
        <form id="compareForm" class="compare-form">
            <div>
                <label class="form-label">Mes base</label>
                <select id="compareA" class="form-select">
                    @foreach($reportsForCompare as $report)
                    <option value="{{ $report->id }}">{{ str_pad($report->period_month, 2, '0', STR_PAD_LEFT) }}/{{ $report->period_year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Mes comparado</label>
                <select id="compareB" class="form-select">
                    @foreach($reportsForCompare as $report)
                    <option value="{{ $report->id }}">{{ str_pad($report->period_month, 2, '0', STR_PAD_LEFT) }}/{{ $report->period_year }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Comparar</button>
        </form>
        <div id="comparePanels" class="compare-result"></div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Comparativo por dia de semana</h2></div>
        <div class="chart-box"><canvas id="compareChart"></canvas></div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Todos los platos vendidos</h2></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Plato</th><th>Cant.</th><th>Ventas</th><th>Costo</th><th>Ganancia</th></tr></thead>
                <tbody>
                    @forelse($allProducts as $item)
                    <tr><td class="rank-number">{{ $loop->iteration }}</td><td>{{ $item['product'] }}</td><td>{{ number_format($item['quantity'], 0, ',', '.') }}</td><td>${{ number_format($item['sales'], 0, ',', '.') }}</td><td>${{ number_format($item['ingredient_cost'], 0, ',', '.') }}</td><td>${{ number_format($item['gross_profit'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Aun no hay platos vendidos este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Todos los insumos usados</h2></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Insumo</th><th>Uso</th><th>Unidad</th><th>Costo</th></tr></thead>
                <tbody>
                    @forelse($allSupplies as $item)
                    <tr><td class="rank-number">{{ $loop->iteration }}</td><td>{{ $item['supply'] }}</td><td>{{ number_format($item['quantity_used'], 3, ',', '.') }}</td><td>{{ $item['unit'] }}</td><td>${{ number_format($item['estimated_cost'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Aun no hay consumo de insumos este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Todas las mesas por venta</h2></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Mesa</th><th>Ordenes</th><th>Venta menu</th></tr></thead>
                <tbody>
                    @forelse($allTables as $item)
                    <tr><td class="rank-number">{{ $loop->iteration }}</td><td>Mesa {{ $item['table_number'] }}</td><td>{{ $item['orders_count'] }}</td><td>${{ number_format($item['sales'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aun no hay ventas por mesa este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Dias con mas venta</h2></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Fecha</th><th>Ordenes</th><th>Venta menu</th></tr></thead>
                <tbody>
                    @forelse($allDays as $day)
                    <tr><td class="rank-number">{{ $loop->iteration }}</td><td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</td><td>{{ $day['orders_count'] }}</td><td>${{ number_format($day['sales'], 0, ',', '.') }}</td></tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Aun no hay ventas este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Dias de semana con mas venta</h2></div>
        <div class="table-scroll" style="max-height:260px;">
            <table class="data-table">
                <thead><tr><th>Dia</th><th>Ordenes</th><th>Venta menu</th></tr></thead>
                <tbody>
                    @foreach($monthlySnapshot['day_of_week_ranking'] ?? [] as $row)
                    <tr><td>{{ $row['day_name'] }}</td><td>{{ $row['orders_count'] }}</td><td>${{ number_format($row['sales'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card chart-card">
        <div class="card-header"><h2 class="card-title">Ventas por franja horaria</h2></div>
        <div class="chart-box"><canvas id="hourlyChart"></canvas></div>
    </div>
</div>

<div class="card no-print" style="margin-bottom:1.25rem;">
    <div class="card-header"><h2 class="card-title">Consumo Teorico vs Real</h2></div>
    <div class="chart-box tall"><canvas id="consumptionChart"></canvas></div>
</div>

@if($recentInventories->isNotEmpty())
<div class="card no-print">
    <div class="card-header"><h2 class="card-title">Conteos Fisicos Recientes</h2></div>
    <table class="data-table">
        <thead><tr><th>Fecha</th><th>Administrador</th><th></th></tr></thead>
        <tbody>
            @foreach($recentInventories as $inv)
            <tr><td>{{ $inv->recorded_at->format('d/m/Y H:i') }}</td><td>{{ $inv->admin->name }}</td><td><a href="{{ route('admin.inventory-results', $inv) }}" class="btn btn-ghost btn-sm">Ver resultados</a></td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection

@push('scripts')
<script>
const chartData = @json($chartData);
const moneyTick = value => '$' + Number(value || 0).toLocaleString('es-CO');
const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#94a3b8' } } },
    scales: {
        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.25)' } },
        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.25)' } },
    },
};

function mountChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return null;
    return new Chart(el, config);
}

mountChart('profitChart', {
    type: 'doughnut',
    data: { labels: chartData.profit_breakdown.labels, datasets: [{ data: chartData.profit_breakdown.values, backgroundColor: ['#f59e0b', '#22c55e'], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } },
});

mountChart('productsChart', {
    type: 'bar',
    data: { labels: chartData.products.labels, datasets: [{ label: 'Unidades vendidas', data: chartData.products.values, backgroundColor: 'rgba(34,197,94,0.72)' }] },
    options: { ...baseOptions, indexAxis: 'y' },
});

mountChart('suppliesChart', {
    type: 'bar',
    data: { labels: chartData.supplies.labels, datasets: [{ label: 'Cantidad usada', data: chartData.supplies.values, backgroundColor: 'rgba(245,158,11,0.76)' }] },
    options: { ...baseOptions, indexAxis: 'y' },
});

mountChart('tablesChart', {
    type: 'bar',
    data: { labels: chartData.tables.labels, datasets: [{ label: 'Venta menu', data: chartData.tables.values, backgroundColor: 'rgba(99,102,241,0.72)' }] },
    options: { ...baseOptions, scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, ticks: { color: '#94a3b8', callback: moneyTick } } } },
});

mountChart('weekChart', {
    type: 'line',
    data: { labels: chartData.day_of_week.labels, datasets: [{ label: 'Venta menu', data: chartData.day_of_week.values, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.18)', tension: 0.35, fill: true }] },
    options: { ...baseOptions, scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, ticks: { color: '#94a3b8', callback: moneyTick } } } },
});

mountChart('dailyChart', {
    type: 'bar',
    data: { labels: chartData.daily_sales.labels, datasets: [{ label: 'Venta diaria', data: chartData.daily_sales.values, backgroundColor: 'rgba(14,165,233,0.72)' }] },
    options: { ...baseOptions, scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, ticks: { color: '#94a3b8', callback: moneyTick } } } },
});

mountChart('hourlyChart', {
    type: 'bar',
    data: { labels: chartData.hourly.labels, datasets: [{ label: 'Venta por hora', data: chartData.hourly.values, backgroundColor: 'rgba(168,85,247,0.72)' }] },
    options: { ...baseOptions, scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, ticks: { color: '#94a3b8', callback: moneyTick } } } },
});

mountChart('consumptionChart', {
    type: 'bar',
    data: {
        labels: @json($comparison['labels'] ?? []),
        datasets: [
            { label: 'Teorico (ventas)', data: @json($comparison['theoretical'] ?? []), backgroundColor: 'rgba(99,102,241,0.7)' },
            { label: 'Real (ventas + merma)', data: @json($comparison['real'] ?? []), backgroundColor: @json($comparison['colors'] ?? []) },
        ],
    },
    options: baseOptions,
});

let compareChart = mountChart('compareChart', {
    type: 'bar',
    data: { labels: [], datasets: [] },
    options: { ...baseOptions, scales: { ...baseOptions.scales, y: { ...baseOptions.scales.y, ticks: { color: '#94a3b8', callback: moneyTick } } } },
});

document.getElementById('compareForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const a = document.getElementById('compareA')?.value;
    const b = document.getElementById('compareB')?.value;
    if (!a || !b) return;

    const params = new URLSearchParams();
    params.append('reports[]', a);
    params.append('reports[]', b);

    const response = await fetch(`{{ route('admin.monthly-reports.compare') }}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    const reports = payload.reports || [];

    document.getElementById('comparePanels').innerHTML = reports.map((report, idx) => {
        const totals = report.totals || {};
        const other = reports[idx === 0 ? 1 : 0]?.totals || {};
        const delta = Number(totals.gross_profit || 0) - Number(other.gross_profit || 0);
        const cls = delta >= 0 ? 'up' : 'down';
        return `<div class="compare-panel">
            <div class="kpi-label">${report.label}</div>
            <div class="kpi-value">${moneyTick(totals.gross_profit)}</div>
            <div class="delta ${cls}">${delta >= 0 ? '+' : ''}${moneyTick(delta)} vs otro mes</div>
            <p class="muted-note">Ventas ${moneyTick(totals.sales)} | Costo ${moneyTick(totals.ingredient_cost)} | Margen ${Number(totals.profit_margin || 0).toFixed(2)}%</p>
        </div>`;
    }).join('');

    compareChart.data.labels = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
    compareChart.data.datasets = reports.map((report, index) => ({
        label: report.label,
        data: (report.day_of_week_ranking || []).map(row => row.sales),
        backgroundColor: index === 0 ? 'rgba(99,102,241,0.72)' : 'rgba(34,197,94,0.72)',
    }));
    compareChart.update();
});
</script>
@endpush
