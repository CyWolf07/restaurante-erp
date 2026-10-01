<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Z — {{ $fiscal_date->format('d/m/Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; font-size: 10px; text-transform: uppercase; }
        .totals { margin-top: 16px; }
        .totals td { border: none; padding: 4px 0; }
        .totals .label { font-weight: bold; width: 60%; }
        .totals .value { text-align: right; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 9px; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $restaurant_name }}</h1>
        <p>INFORME Z — CIERRE DIARIO FISCAL</p>
        <p>Fecha fiscal: {{ $fiscal_date->format('d/m/Y') }} | Generado: {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>Cajero: {{ $cashier->name }}</p>
    </div>

    <table class="totals">
        <tr><td class="label">Total ventas (con impuesto)</td><td class="value">${{ number_format($total_sales, 2) }}</td></tr>
        <tr><td class="label">Subtotal neto</td><td class="value">${{ number_format($total_net ?? ($total_sales - $total_tax), 2) }}</td></tr>
        <tr><td class="label">Impuestos</td><td class="value">${{ number_format($total_tax, 2) }}</td></tr>
        <tr><td class="label">Órdenes pagadas</td><td class="value">{{ $total_orders_count ?? 0 }}</td></tr>
        <tr><td class="label">Órdenes canceladas</td><td class="value">{{ $cancelled_orders_count }}</td></tr>
        <tr><td class="label">Monto cancelado</td><td class="value">${{ number_format($total_cancelled_amount, 2) }}</td></tr>
        <tr><td class="label">Documentos fiscales sin enviar (acumulado al cierre)</td><td class="value">{{ $fiscal_pending_count ?? 0 }}</td></tr>
        <tr><td class="label">Valor pendiente de transmisión (acumulado)</td><td class="value">${{ number_format($fiscal_pending_total ?? 0, 2) }}</td></tr>
    </table>

    @if(!empty($summary_data['by_product']))
    <h3 style="font-size:12px;margin-top:20px;">Detalle por producto</h3>
    <table>
        <thead>
            <tr><th>Categoría</th><th>Producto</th><th>Cant.</th><th>Importe</th></tr>
        </thead>
        <tbody>
            @foreach($summary_data['by_product'] as $item)
            <tr>
                <td>{{ $item['category'] }}</td>
                <td>{{ $item['product'] }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td>${{ number_format($item['amount'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Documento interno generado localmente. El valor pendiente de transmisión requiere conciliación y envío por el sistema fiscal autorizado.
    </div>
</body>
</html>
