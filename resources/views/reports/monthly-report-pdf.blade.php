<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre mensual - {{ $snapshot['period']['label'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; text-align: center; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 3px 0; color: #4b5563; }
        .metric { width: 15.3%; display: inline-block; border: 1px solid #d1d5db; padding: 7px; margin-right: 0.5%; vertical-align: top; min-height: 44px; }
        .metric .label { color: #6b7280; font-size: 7px; text-transform: uppercase; }
        .metric .value { font-size: 12px; font-weight: bold; margin-top: 4px; }
        h2 { font-size: 11px; margin: 15px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; }
        th { background: #f3f4f6; font-size: 7px; text-transform: uppercase; color: #374151; }
        .num { text-align: right; }
        .bar-wrap { background: #e5e7eb; height: 9px; width: 100%; border-radius: 3px; overflow: hidden; }
        .bar { background: #2563eb; height: 9px; border-radius: 3px; }
        .bar.green { background: #16a34a; }
        .bar.orange { background: #f97316; }
        .bar.indigo { background: #6366f1; }
        .bar.purple { background: #9333ea; }
        .section-note { color: #6b7280; font-size: 8px; margin: 0 0 6px; }
        .footer { margin-top: 18px; font-size: 8px; color: #6b7280; text-align: center; border-top: 1px solid #d1d5db; padding-top: 8px; }
    </style>
</head>
<body>
@if($snapshot['totals']['cost_incomplete'] ?? false)
<p role="alert" style="padding:12px;border:1px solid #b45309">Costos históricos incompletos: existen consumos antiguos sin costo registrado. Los costos mostrados son parciales y el margen no debe interpretarse como resultado definitivo.</p>
@endif
    @php
        $totals = $snapshot['totals'] ?? [];
        $products = collect($snapshot['products'] ?? []);
        $supplies = collect($snapshot['supplies'] ?? []);
        $tables = collect($snapshot['tables'] ?? []);
        $days = collect($snapshot['days'] ?? []);
        $weekDays = collect($snapshot['day_of_week_ranking'] ?? []);
        $hours = collect($snapshot['hourly_ranking'] ?? []);
        $categories = collect($snapshot['category_margins'] ?? []);
        $maxProductQty = max(1, (float) $products->max('quantity'));
        $maxSupplyQty = max(1, (float) $supplies->max('quantity_used'));
        $maxTableSales = max(1, (float) $tables->max('sales'));
        $maxDaySales = max(1, (float) $days->max('sales'));
        $maxWeekSales = max(1, (float) $weekDays->max('sales'));
        $maxHourSales = max(1, (float) $hours->max('sales'));
        $maxCategoryProfit = max(1, (float) $categories->max('gross_profit'));
    @endphp

    <div class="header">
        <h1>{{ $restaurant_name }}</h1>
        <p>CIERRE MENSUAL ADMINISTRATIVO</p>
        <p>Periodo: {{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}</p>
        <p>Cerrado por: {{ $report->closer->name ?? 'Administrador' }} | Generado: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div>
        <div class="metric"><div class="label">Ventas menu</div><div class="value">${{ number_format($totals['sales'] ?? 0, 2) }}</div></div>
        <div class="metric"><div class="label">Costo insumos</div><div class="value">${{ number_format($totals['ingredient_cost'] ?? 0, 2) }}</div></div>
        <div class="metric"><div class="label">Ganancia bruta</div><div class="value">${{ number_format($totals['gross_profit'] ?? 0, 2) }}</div></div>
        <div class="metric"><div class="label">Margen</div><div class="value">{{ number_format($totals['profit_margin'] ?? 0, 2) }}%</div></div>
        <div class="metric"><div class="label">Ordenes</div><div class="value">{{ number_format($totals['orders_count'] ?? 0) }}</div></div>
        <div class="metric"><div class="label">Platos</div><div class="value">{{ number_format($totals['products_sold'] ?? 0) }}</div></div>
    </div>

    <h2>Ventas por dia de la semana</h2>
    <table>
        <thead><tr><th>Dia</th><th class="num">Ordenes</th><th class="num">Venta menu</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($weekDays as $item)
            <tr>
                <td>{{ $item['day_name'] }}</td>
                <td class="num">{{ $item['orders_count'] }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar green" style="width:{{ min(100, ($item['sales'] / $maxWeekSales) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($hours->isNotEmpty())
    <h2>Ranking de franjas horarias</h2>
    <table>
        <thead><tr><th>Hora</th><th class="num">Ordenes</th><th class="num">Venta menu</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($hours as $item)
            <tr>
                <td>{{ $item['hour_label'] }}</td>
                <td class="num">{{ $item['orders_count'] }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar purple" style="width:{{ min(100, ($item['sales'] / $maxHourSales) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($categories->isNotEmpty())
    <h2>Margen por categoria de plato</h2>
    <table>
        <thead><tr><th>Categoria</th><th class="num">Cant.</th><th class="num">Ventas</th><th class="num">Costo</th><th class="num">Ganancia</th><th class="num">Margen</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($categories as $item)
            <tr>
                <td>{{ $item['category'] }}</td>
                <td class="num">{{ number_format($item['quantity']) }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td class="num">${{ number_format($item['ingredient_cost'], 2) }}</td>
                <td class="num">${{ number_format($item['gross_profit'], 2) }}</td>
                <td class="num">{{ number_format($item['profit_margin'], 2) }}%</td>
                <td><div class="bar-wrap"><div class="bar indigo" style="width:{{ min(100, ($item['gross_profit'] / $maxCategoryProfit) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h2>Platos vendidos completos</h2>
    <p class="section-note">Orden descendente por unidades vendidas.</p>
    <table>
        <thead><tr><th>#</th><th>Plato</th><th>Categoria</th><th class="num">Cant.</th><th class="num">Ventas</th><th class="num">Costo</th><th class="num">Ganancia</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($products as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['product'] }}</td>
                <td>{{ $item['category'] }}</td>
                <td class="num">{{ number_format($item['quantity']) }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td class="num">${{ number_format($item['ingredient_cost'], 2) }}</td>
                <td class="num">${{ number_format($item['gross_profit'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar green" style="width:{{ min(100, ($item['quantity'] / $maxProductQty) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Insumos usados completos</h2>
    <p class="section-note">Orden descendente por cantidad utilizada en ventas.</p>
    <table>
        <thead><tr><th>#</th><th>Insumo</th><th class="num">Uso</th><th>Unidad</th><th class="num">Costo estimado</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($supplies as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['supply'] }}</td>
                <td class="num">{{ number_format($item['quantity_used'], 3) }}</td>
                <td>{{ $item['unit'] }}</td>
                <td class="num">${{ number_format($item['estimated_cost'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar orange" style="width:{{ min(100, ($item['quantity_used'] / $maxSupplyQty) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Mesas por venta</h2>
    <table>
        <thead><tr><th>#</th><th>Mesa</th><th class="num">Ordenes</th><th class="num">Venta menu</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($tables as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>Mesa {{ $item['table_number'] }}</td>
                <td class="num">{{ $item['orders_count'] }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar" style="width:{{ min(100, ($item['sales'] / $maxTableSales) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Dias con mas venta</h2>
    <table>
        <thead><tr><th>#</th><th>Fecha</th><th class="num">Ordenes</th><th class="num">Venta menu</th><th>Grafica</th></tr></thead>
        <tbody>
            @foreach($days as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($item['date'])->format('d/m/Y') }}</td>
                <td class="num">{{ $item['orders_count'] }}</td>
                <td class="num">${{ number_format($item['sales'], 2) }}</td>
                <td><div class="bar-wrap"><div class="bar" style="width:{{ min(100, ($item['sales'] / $maxDaySales) * 100) }}%;"></div></div></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Snapshot mensual comprimido y guardado dentro del programa para comparaciones futuras.
    </div>
</body>
</html>
