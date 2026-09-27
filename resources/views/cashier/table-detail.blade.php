@extends('layouts.app')
@section('title', (($restaurantTable->display_name ?? "Mesa #$table") . ' — Detalle'))
@push('styles')
<style>
    .detail-layout { display:grid; grid-template-columns:2fr 1fr; gap:1.5rem; }
    .add-product-grid { display:grid; grid-template-columns:minmax(0, 1fr) 90px 130px 130px; gap:0.75rem; align-items:end; }
    .price-preview { border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-input); padding:0.65rem 0.75rem; font-weight:700; min-height:42px; display:flex; align-items:center; justify-content:flex-end; }
    .detail-actions { display:flex; gap:0.45rem; flex-wrap:wrap; }
    .edit-detail-row[hidden] { display:none; }
    .edit-detail-panel { border:1px solid var(--border); border-radius:var(--radius-sm); background:rgba(15,23,42,0.28); padding:1rem; }
    .edit-detail-grid { display:grid; grid-template-columns:minmax(0, 1fr) 90px 130px 130px; gap:0.75rem; align-items:end; }
    .edit-detail-footer { display:flex; justify-content:space-between; gap:1rem; align-items:center; margin-top:0.75rem; flex-wrap:wrap; }
    @media (max-width: 900px) {
        .detail-layout { grid-template-columns:1fr; }
        .add-product-grid, .edit-detail-grid { grid-template-columns:1fr 1fr; }
    }
    @media (max-width: 560px) {
        .add-product-grid, .edit-detail-grid { grid-template-columns:1fr; }
        .price-preview { justify-content:flex-start; }
    }
    .opt-chip-td {
        display:inline-flex; align-items:center; gap:0.3rem; padding:0.3rem 0.7rem; border-radius:999px;
        font-size:0.75rem; font-weight:600; border:1px solid var(--border); background:var(--bg-input);
        cursor:pointer; transition:var(--transition); user-select:none;
    }
    .opt-chip-td.active { background:rgba(99,102,241,0.25); border-color:var(--accent); color:var(--accent-hover); }
    .opt-chip-td.radio-mode.active { background:rgba(34,197,94,0.2); border-color:var(--success); color:var(--success); }
    .opt-chip-td:hover { border-color:var(--accent-hover); }
    .opt-group-lbl { font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin:0.5rem 0 0.25rem; }
</style>
@endpush
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $restaurantTable->display_name ?? ('Mesa #'.$table) }}</h1>
        <p class="page-subtitle">
            @if($order) Estado: <span class="badge badge-{{ $order->status_color }}">{{ $order->status_label }}</span>
            @else Sin orden activa @endif
        </p>
    </div>
    <a href="{{ route('cashier.pos') }}" class="btn btn-ghost">← Volver al Mapa</a>
</div>

@if(!$order)
    <div class="card" style="text-align:center;padding:3rem;">
        <p style="font-size:3rem;">{{ str_contains($restaurantTable->display_name ?? '', 'Domicilio') ? '🛵' : '🪑' }}</p>
        <p style="color:var(--text-muted);margin-top:1rem;">{{ str_contains($restaurantTable->display_name ?? '', 'Domicilio') ? 'Este domicilio está libre.' : 'Esta mesa está libre.' }}</p>
    </div>
@else
<div class="detail-layout">
    <div class="card">
        <h3 style="font-weight:700;margin-bottom:1rem;">📝 Detalle de la Orden</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Mesero: {{ $order->waiter->name ?? 'N/A' }} · {{ $order->created_at->format('H:i') }}</p>
        <table class="data-table">
            <thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Descuento</th><th>Subtotal</th><th>Notas</th><th>Accion</th></tr></thead>
            <tbody>
                @foreach($order->details as $d)
                <tr>
                    <td style="font-weight:600;">{{ $d->product->name }}</td>
                    <td>{{ $d->quantity }}</td>
                    <td>{{ cop($d->unit_price) }}</td>
                    <td>{{ cop($d->discount ?? 0) }}</td>
                    <td>{{ cop($d->subtotal) }}</td>
                    <td style="font-size:0.75rem;color:var(--text-muted);">{{ $d->comments ?? '—' }}</td>
                    <td>
                        @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
                        <div class="detail-actions">
                        <button type="button" class="btn btn-ghost btn-sm edit-detail-toggle" data-target="edit-detail-{{ $d->id }}">Editar</button>
                        <details>
                        <summary class="btn btn-danger btn-sm">Eliminar</summary>
                        <form action="{{ route('cashier.order-details.destroy', $d) }}" method="POST" onsubmit="return confirm('¿Eliminar este plato de la mesa?')">
                            <label class="form-label" for="remove-reason-{{ $d->id }}">Motivo de eliminación</label>
                            <input id="remove-reason-{{ $d->id }}" type="text" name="reason" class="form-input" required minlength="5" maxlength="1000" placeholder="Ej.: cliente cambió de plato">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Confirmar eliminación</button>
                        </form>
                        </details>
                        </div>
                        @endif
                    </td>
                </tr>
                @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
                <tr class="edit-detail-row" id="edit-detail-{{ $d->id }}" hidden>
                    <td colspan="7">
                        <form action="{{ route('cashier.order-details.update', $d) }}" method="POST" class="edit-detail-panel js-edit-detail-form">
                            @csrf
                            @method('PUT')
                            <div class="edit-detail-grid">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">Producto</label>
                                    <select name="product_id" class="form-select js-edit-product" required>
                                        @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ (float) $product->price }}" @selected($product->id === $d->product_id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" name="quantity" class="form-input js-edit-quantity" value="{{ $d->quantity }}" min="1" step="1" required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">Valor</label>
                                    <div class="price-preview js-edit-price">{{ cop($d->unit_price) }}</div>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label">Descuento</label>
                                    <input type="number" name="discount" class="form-input js-edit-discount" value="{{ (float) ($d->discount ?? 0) }}" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:0.75rem;">
                                <label class="form-label">Notas</label>
                                <input type="text" name="comments" class="form-input" value="{{ $d->comments }}" placeholder="Comentarios cocina...">
                            </div>
                            <div class="edit-detail-footer">
                                <div style="font-size:0.9rem;color:var(--text-muted);">
                                    Subtotal editado: <strong class="js-edit-subtotal" style="color:var(--text-primary);">{{ cop($d->subtotal) }}</strong>
                                </div>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                    <button type="button" class="btn btn-ghost btn-sm edit-detail-cancel" data-target="edit-detail-{{ $d->id }}">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
                @endif
                @if($d->modifiers->count())
                    @foreach($d->modifiers as $m)
                    <tr style="background:rgba(99,102,241,0.05);">
                        <td style="padding-left:2rem;font-size:0.8rem;">+ {{ $m->modifier->name }}</td>
                        <td>{{ $m->quantity }}</td>
                        <td>{{ cop($m->unit_price) }}</td>
                        <td>{{ cop(0) }}</td>
                        <td>{{ cop($m->subtotal) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endforeach
                @endif
                @endforeach
            </tbody>
        </table>

        @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
        <div style="border-top:1px solid var(--border);margin-top:1.25rem;padding-top:1.25rem;">
            <h3 style="font-weight:700;margin-bottom:1rem;">Agregar plato/producto</h3>
            <form action="{{ route('cashier.order-details.store', $order) }}" method="POST" id="add-product-form" onsubmit="return prepareOptionsSubmit()">
                @csrf
                <div class="add-product-grid">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Producto</label>
                        <select name="product_id" id="product-select" class="form-select" required>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}" data-price="{{ (float) $product->price }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Cantidad</label>
                        <input type="number" name="quantity" id="product-quantity" class="form-input" value="1" min="1" step="1" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Valor</label>
                        <div class="price-preview" id="product-price-preview">{{ cop($products->first()?->price ?? 0) }}</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Descuento</label>
                        <input type="number" name="discount" id="product-discount" class="form-input" value="0" min="0" step="0.01">
                    </div>
                </div>

                {{-- Panel de opciones dinámico --}}
                <div id="options-panel" style="margin-top:0.75rem;display:none;">
                    <label class="form-label">🧩 Opciones de preparación</label>
                    <div id="options-chips" style="display:flex;flex-wrap:wrap;gap:0.35rem;"></div>
                </div>

                <div class="form-group" style="margin-top:0.75rem;">
                    <label class="form-label">Notas</label>
                    <input type="text" name="comments" id="product-comments" class="form-input" placeholder="Comentarios cocina...">
                </div>
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;">
                    <div style="font-size:0.9rem;color:var(--text-muted);">
                        Subtotal nuevo: <strong id="new-product-subtotal" style="color:var(--text-primary);">{{ cop(0) }}</strong>
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar a la mesa</button>
                </div>
            </form>
        </div>
        @endif
    </div>

    <div>
        <div class="card" style="margin-bottom:1rem;">
            <h3 style="font-weight:700;margin-bottom:1rem;">💰 Resumen</h3>
            @php $discountTotal = $order->details->sum(fn($detail) => (float) ($detail->discount ?? 0)); @endphp
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;color:var(--text-muted);"><span>Descuento:</span><span>{{ cop($discountTotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;"><span>Subtotal:</span><span>{{ cop($order->subtotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;color:var(--text-muted);"><span>IVA ({{ config('app.tax_rate')*100 }}%):</span><span>{{ cop($order->tax) }}</span></div>
            <div style="display:flex;justify-content:space-between;padding-top:0.75rem;border-top:2px solid var(--border);font-size:1.5rem;font-weight:800;color:var(--accent-hover);"><span>TOTAL:</span><span>{{ cop($order->total) }}</span></div>
        </div>

        @if(!$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
            @if($order->isInKitchen())
            <form action="{{ route('cashier.mark-ready', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">✅ Marcar como Listo</button>
            </form>
            @endif

            @if($order->isPending())
            <form action="{{ route('cashier.send-kitchen', $order) }}" method="POST" onsubmit="return confirm('¿Enviar comanda a cocina?')">
                @csrf
                <button type="submit" class="btn btn-warning btn-lg" style="width:100%;justify-content:center;">Enviar comanda a cocina</button>
            </form>
            @endif

            @if($order->isReady() || $order->isPending())
            <form action="{{ route('cashier.pay-order', $order) }}" method="POST" onsubmit="return confirm('¿Confirmar cobro de {{ cop($order->total) }}?')">
                @csrf
                <input type="hidden" name="expected_total" value="{{ $order->total }}">
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">💳 Cobrar Orden</button>
            </form>
            @endif

            <form action="{{ route('cashier.transfer-order', $order) }}" method="POST" class="card">
                @csrf
                <label class="form-label" for="transfer-table">Trasladar a otra mesa</label>
                <select id="transfer-table" name="restaurant_table_id" class="form-select" required>
                    <option value="">Selecciona una mesa libre</option>
                    @foreach($availableTables as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->display_name }} · {{ $destination->zone }}</option>
                    @endforeach
                </select>
                <p style="font-size:0.8rem;color:var(--text-muted);margin:0.5rem 0;">Conserva los platos y el total. Avisa a cocina si la comanda ya fue enviada.</p>
                <button type="submit" class="btn btn-ghost" @disabled($availableTables->isEmpty())>Trasladar mesa</button>
            </form>

            <form action="{{ route('cashier.cancel-order', $order) }}" method="POST" onsubmit="return confirm('¿Cancelar esta orden?')">
                @csrf
                <input type="text" name="reason" class="form-input" placeholder="Razón de cancelación..." required style="margin-bottom:0.5rem;">
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center;">❌ Cancelar Orden</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endif
@endsection
@push('scripts')
@if($order && !$order->isPaid() && !$order->isCancelled() && !$order->isLocked())
<script>
const productOptionsData = @json($productOptions);
const productSelect = document.getElementById('product-select');
const productQuantity = document.getElementById('product-quantity');
const productDiscount = document.getElementById('product-discount');
const productPricePreview = document.getElementById('product-price-preview');
const newProductSubtotal = document.getElementById('new-product-subtotal');

function formatCop(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value || 0);
}

function selectedProductPrice() {
    return Number(productSelect?.selectedOptions?.[0]?.dataset?.price || 0);
}

function recalculateNewProduct() {
    const price = selectedProductPrice();
    const quantity = Math.max(1, Number(productQuantity?.value || 1));
    const grossSubtotal = price * quantity;
    const discount = Math.min(Math.max(0, Number(productDiscount?.value || 0)), grossSubtotal);
    productPricePreview.textContent = formatCop(price);
    newProductSubtotal.textContent = formatCop(grossSubtotal - discount);
}

function updateOptionsPanel() {
    const productId = productSelect.value;
    const options = productOptionsData[productId] || {};
    const hasOptions = Object.keys(options).length > 0;

    const panel = document.getElementById('options-panel');
    const chipsContainer = document.getElementById('options-chips');
    chipsContainer.innerHTML = '';

    if (!hasOptions) {
        panel.style.display = 'none';
        return;
    }

    panel.style.display = 'block';

    let html = '';
    for (const [group, opts] of Object.entries(options)) {
        const isSoup = group.toLowerCase() === 'sopa';
        html += `<div style="width:100%;"><div class="opt-group-lbl">${group}</div><div style="display:flex;flex-wrap:wrap;gap:0.35rem;">`;
        opts.forEach(opt => {
            html += `<span class="opt-chip-td ${isSoup ? 'radio-mode' : ''}" data-id="${opt.id}" data-group="${group}" onclick="toggleOptionChip(this, '${isSoup ? 'radio' : 'check'}')">
                ${opt.name}
            </span>`;
        });
        html += `</div></div>`;
    }
    chipsContainer.innerHTML = html;
}

function toggleOptionChip(chip, mode) {
    if (mode === 'radio') {
        const group = chip.dataset.group;
        document.querySelectorAll(`.opt-chip-td[data-group="${group}"]`).forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
    } else {
        chip.classList.toggle('active');
    }
}

function prepareOptionsSubmit() {
    const form = document.getElementById('add-product-form');
    // Eliminar inputs temporales previos
    form.querySelectorAll('.temp-modifier-input').forEach(el => el.remove());

    // Buscar chips activos
    const activeChips = document.querySelectorAll('#options-chips .opt-chip-td.active');
    activeChips.forEach((chip, index) => {
        const modId = chip.dataset.id;

        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = `modifiers[${index}][modifier_id]`;
        inputId.value = modId;
        inputId.className = 'temp-modifier-input';

        const inputQty = document.createElement('input');
        inputQty.type = 'hidden';
        inputQty.name = `modifiers[${index}][quantity]`;
        inputQty.value = '1';
        inputQty.className = 'temp-modifier-input';

        form.appendChild(inputId);
        form.appendChild(inputQty);
    });

    return true;
}

[productSelect, productQuantity, productDiscount].forEach(input => {
    input?.addEventListener('input', recalculateNewProduct);
    input?.addEventListener('change', recalculateNewProduct);
});

productSelect?.addEventListener('change', updateOptionsPanel);

function editFormPrice(form) {
    return Number(form.querySelector('.js-edit-product')?.selectedOptions?.[0]?.dataset?.price || 0);
}

function recalculateEditForm(form) {
    const price = editFormPrice(form);
    const quantity = Math.max(1, Number(form.querySelector('.js-edit-quantity')?.value || 1));
    const grossSubtotal = price * quantity;
    const discount = Math.min(Math.max(0, Number(form.querySelector('.js-edit-discount')?.value || 0)), grossSubtotal);
    form.querySelector('.js-edit-price').textContent = formatCop(price);
    form.querySelector('.js-edit-subtotal').textContent = formatCop(grossSubtotal - discount);
}

document.querySelectorAll('.edit-detail-toggle, .edit-detail-cancel').forEach(button => {
    button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.target);
        if (!row) return;
        row.hidden = !row.hidden;
        if (!row.hidden) {
            const form = row.querySelector('.js-edit-detail-form');
            if (form) recalculateEditForm(form);
        }
    });
});

document.querySelectorAll('.js-edit-detail-form').forEach(form => {
    form.querySelectorAll('.js-edit-product, .js-edit-quantity, .js-edit-discount').forEach(input => {
        input.addEventListener('input', () => recalculateEditForm(form));
        input.addEventListener('change', () => recalculateEditForm(form));
    });
    recalculateEditForm(form);
});

recalculateNewProduct();
updateOptionsPanel();
</script>
@endif
@endpush
