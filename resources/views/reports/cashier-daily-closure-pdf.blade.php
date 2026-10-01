<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cierre de caja - {{ $closure->fiscal_date->format('d/m/Y') }}</title>
    <style>
        @page { margin: 2mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 8.5px;
            line-height: 1.25;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #111;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
        }
        .brand {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 1mm;
        }
        .subtitle {
            font-size: 8px;
            font-weight: 700;
        }
        .meta {
            margin: 0 0 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #777;
        }
        .row {
            width: 100%;
            clear: both;
            padding: 0.7mm 0;
        }
        .label { float: left; width: 56%; }
        .value { float: right; width: 44%; text-align: right; font-weight: 700; }
        .section-title {
            font-weight: 800;
            text-transform: uppercase;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 1mm 0;
            margin: 3mm 0 1.5mm;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
        }
        th {
            text-align: left;
            border-bottom: 1px solid #111;
            font-size: 7.5px;
            padding: 1mm 0;
        }
        td {
            border-bottom: 1px dashed #bbb;
            padding: 1mm 0;
            vertical-align: top;
        }
        .money { text-align: right; white-space: nowrap; }
        .total-box {
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 1.5mm 0;
            margin-top: 2mm;
            font-size: 9px;
        }
        .signature {
            margin-top: 8mm;
            text-align: center;
            border-top: 1px solid #111;
            padding-top: 1mm;
            font-size: 7.5px;
        }
        .footer {
            margin-top: 4mm;
            text-align: center;
            font-size: 7px;
            color: #444;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $restaurant_name }}</div>
        <div class="subtitle">CIERRE DE CAJA DIARIO</div>
    </div>

    <div class="meta">
        <div class="row"><span class="label">Fecha</span><span class="value">{{ $closure->fiscal_date->format('d/m/Y') }}</span></div>
        <div class="row"><span class="label">Hora cierre</span><span class="value">{{ $closure->closed_at->format('H:i:s') }}</span></div>
        <div class="row"><span class="label">Cajero</span><span class="value">{{ $closure->cashier->name }}</span></div>
    </div>

    <div class="section-title">Resumen de ventas</div>
    <div class="row"><span class="label">Ventas brutas</span><span class="value">{{ cop($closure->total_sales) }}</span></div>
    <div class="row"><span class="label">Ventas en efectivo</span><span class="value">{{ isset($closure->cash_count_summary['cash_sales']) ? cop($closure->cash_count_summary['cash_sales']) : 'No registrado (histórico)' }}</span></div>
    <div class="row"><span class="label">Ventas sin efectivo</span><span class="value">{{ isset($closure->cash_count_summary['non_cash_sales']) ? cop($closure->cash_count_summary['non_cash_sales']) : 'No registrado (histórico)' }}</span></div>
    <div class="row"><span class="label">Subtotal neto</span><span class="value">{{ cop($closure->total_net) }}</span></div>
    <div class="row"><span class="label">Impuestos</span><span class="value">{{ cop($closure->total_tax) }}</span></div>
    <div class="row"><span class="label">Ordenes pagadas</span><span class="value">{{ $closure->total_orders_count }}</span></div>
    <div class="row"><span class="label">Ordenes canceladas</span><span class="value">{{ $closure->cancelled_orders_count }}</span></div>
    <div class="row"><span class="label">Valor cancelado</span><span class="value">{{ cop($closure->total_cancelled_amount) }}</span></div>

    <div class="section-title">Control fiscal pendiente</div>
    <div class="row"><span class="label">Documentos sin enviar (acumulado)</span><span class="value">{{ $closure->fiscal_pending_count }}</span></div>
    <div class="row"><span class="label">Valor pendiente al cierre</span><span class="value">{{ cop($closure->fiscal_pending_total) }}</span></div>

    <div class="section-title">Gastos del dia</div>
    @if(!empty($closure->expenses))
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="money">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($closure->expenses as $expense)
                <tr>
                    <td>{{ $expense['concept'] }}</td>
                    <td class="money">{{ cop($expense['amount']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="row"><span class="label">Sin gastos registrados</span><span class="value">{{ cop(0) }}</span></div>
    @endif

    <div class="total-box">
        <div class="row"><span class="label">Total gastos</span><span class="value">{{ cop($closure->total_expenses) }}</span></div>
        <div class="row"><span class="label">Ventas - gastos</span><span class="value">{{ cop($closure->expected_cash_total) }}</span></div>
    </div>

    <div class="section-title">Arqueo de caja</div>
    <div class="row"><span class="label">Base</span><span class="value">{{ cop($closure->base_cash_total) }}</span></div>
    <div class="row"><span class="label">Moneda de cambio</span><span class="value">{{ cop($closure->change_cash_total) }}</span></div>
    <div class="row"><span class="label">Venta contada</span><span class="value">{{ cop($closure->sales_cash_total) }}</span></div>
    <div class="row"><span class="label">Total contado</span><span class="value">{{ cop($closure->declared_cash_total) }}</span></div>
    <div class="row"><span class="label">Diferencia caja</span><span class="value">{{ cop($closure->cash_difference) }}</span></div>

    @php
        $cashSections = [
            'base' => 'Base',
            'change' => 'Cambio',
            'sales' => 'Venta',
        ];
    @endphp
    @foreach($cashSections as $key => $label)
        @if(!empty($closure->cash_count_summary[$key]))
            <table>
                <thead><tr><th>{{ $label }}</th><th class="money">Cant.</th><th class="money">Total</th></tr></thead>
                <tbody>
                    @foreach($closure->cash_count_summary[$key] as $denomination => $quantity)
                        @if((int) $quantity > 0)
                        <tr>
                            <td>{{ cop($denomination) }}</td>
                            <td class="money">{{ $quantity }}</td>
                            <td class="money">{{ cop((int) $denomination * (int) $quantity) }}</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if(!empty($closure->cash_count_summary['notes']))
        <div class="row"><span class="label">Notas</span><span class="value">{{ $closure->cash_count_summary['notes'] }}</span></div>
    @endif

    <div class="section-title">Comparativa Informe Z</div>
    @if($closure->report_z_total_sales !== null)
        <div class="row"><span class="label">Total Informe Z</span><span class="value">{{ cop($closure->report_z_total_sales) }}</span></div>
        <div class="row"><span class="label">Diferencia ventas</span><span class="value">{{ cop($closure->difference_vs_report_z) }}</span></div>
    @else
        <div class="row"><span class="label">Informe Z</span><span class="value">No generado</span></div>
    @endif

    <div class="signature">Firma cajero</div>
    <div class="footer">Documento interno para comparativa administrativa. Papel 80mm, margen 2mm.</div>
</body>
</html>
