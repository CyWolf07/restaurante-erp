@extends('layouts.app')
@section('title', 'Nueva Orden — Comandero Digital')
@push('styles')
<style>
    .commander { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 380px); gap: 1.5rem; min-height: calc(100vh - 8rem); }
    @media (max-width: 1100px) { .commander { grid-template-columns: minmax(0, 1fr); } }
    .menu-area { overflow-y: auto; }
    .cart-area { position: sticky; top: 2rem; }
    .category-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .category-tab {
        padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600;
        border: 1px solid var(--border); background: transparent; color: var(--text-secondary);
        cursor: pointer; transition: var(--transition); font-family: inherit;
    }
    .category-tab.active { background: var(--accent); color: white; border-color: var(--accent); }
    .product-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
    .product-card {
        background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
        padding: 0; cursor: pointer; transition: var(--transition); overflow: hidden;
    }
    .product-card:hover { border-color: var(--accent); transform: translateY(-2px); }
    .product-card-img { height: 100px; background: var(--bg-input); display: flex; align-items: center; justify-content: center; font-size: 2rem; }
    .product-card-body { padding: 0.75rem; }
    .product-card-name { font-size: 0.8rem; font-weight: 700; }
    .product-card-price { font-size: 0.85rem; color: var(--accent-hover); font-weight: 700; margin-top: 0.25rem; }
    .cart-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid var(--border); }
    .cart-item-qty { display: flex; align-items: center; gap: 0.5rem; }
    .qty-btn {
        width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border);
        background: transparent; color: var(--text-primary); cursor: pointer; font-size: 1rem;
        display: flex; align-items: center; justify-content: center; font-family: inherit;
        transition: var(--transition);
    }
    .qty-btn:hover { background: var(--accent); border-color: var(--accent); color: white; }
    .modifier-list { margin-top: 0.5rem; padding-left: 1rem; }
    .modifier-check { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; font-size: 0.8rem; }
    .modifier-check input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--accent); }

    /* Options Modal */
    .options-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:200; align-items:center; justify-content:center; }
    .options-overlay.show { display:flex; }
    .options-modal { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:1.5rem; width:480px; max-width:95vw; max-height:85vh; overflow-y:auto; }
    .opt-group-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); margin:0.75rem 0 0.4rem; }
    .opt-group-label:first-child { margin-top:0; }
    .opt-chips { display:flex; flex-wrap:wrap; gap:0.4rem; }
    .opt-chip {
        display:inline-flex; align-items:center; gap:0.35rem; padding:0.4rem 0.8rem; border-radius:999px;
        font-size:0.8rem; font-weight:600; border:1px solid var(--border); background:var(--bg-input);
        cursor:pointer; transition:var(--transition); user-select:none;
    }
    .opt-chip.active { background:rgba(99,102,241,0.25); border-color:var(--accent); color:var(--accent-hover); }
    .opt-chip:hover { border-color:var(--accent-hover); }
    .opt-chip.radio-mode.active { background:rgba(34,197,94,0.2); border-color:var(--success); color:var(--success); }
    .cart-options { display:flex; flex-wrap:wrap; gap:0.3rem; margin-top:0.25rem; }
    .cart-option-tag { font-size:0.65rem; padding:0.15rem 0.45rem; border-radius:999px; background:rgba(99,102,241,0.15); color:var(--accent-hover); }
</style>
@endpush
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">+ {{ $screenTitle ?? 'Nueva Orden' }}</h1>
        <p class="page-subtitle">{{ $screenSubtitle ?? 'Selecciona los platos y envia a cocina' }}</p>
    </div>
</div>

<div class="commander">
    <div class="menu-area">
        <div class="category-tabs">
            <button class="category-tab active" onclick="filterCategory('all', this)">Todos</button>
            @foreach($categories as $cat)
            <button class="category-tab" onclick="filterCategory('{{ $cat->id }}', this)" style="--cat-color:{{ $cat->color }};">{{ $cat->name }}</button>
            @endforeach
        </div>
        <div class="product-grid" id="product-grid">
            @foreach($categories as $cat)
            @foreach($cat->products as $product)
            <div class="product-card" data-category="{{ $cat->id }}" data-product="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price }}" onclick="onProductClick(this.dataset.product, this.dataset.name, Number(this.dataset.price))">
                <div class="product-card-img">🍽️</div>
                <div class="product-card-body">
                    <div class="product-card-name">{{ $product->name }}</div>
                    <div class="product-card-price">{{ cop($product->price) }}</div>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
    </div>

    <div class="cart-area">
        <div class="card" style="position:sticky;top:2rem;">
            <h3 style="font-size:1rem;font-weight:800;margin-bottom:0.5rem;">🛒 Orden Actual</h3>
            <div class="form-group">
                <label class="form-label">{{ str_contains($screenTitle ?? '', 'Domicilio') ? 'Domicilio' : 'Mesa' }}</label>
                <select class="form-select" id="table-select" required>
                    @foreach($tables as $t)
                    @php $busy = $t->activeOrder(); @endphp
                    <option value="{{ $t->id }}" {{ $busy ? 'disabled' : '' }}>
                        {{ $t->display_name }} — {{ $t->zone }}{{ $busy ? ' (OCUPADA)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div id="cart-items" style="max-height:50vh;overflow-y:auto;">
                <p style="color:var(--text-muted);font-size:0.85rem;text-align:center;padding:2rem 0;">Agrega platos del menú</p>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem;">
                <div style="display:flex;justify-content:space-between;font-size:1.25rem;font-weight:800;margin-bottom:1rem;">
                    <span>Total:</span><span id="cart-total">$0.00</span>
                </div>
                <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;" onclick="submitOrder()">
                    📤 Crear Orden
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal de opciones de plato --}}
<div class="options-overlay" id="options-overlay">
    <div class="options-modal">
        <h3 style="font-weight:700;margin-bottom:0.25rem;" id="options-product-name"></h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Selecciona las opciones de preparación</p>
        <div id="options-container"></div>
        <div class="form-group" style="margin-top:1rem;">
            <label class="form-label">Otros / Comentarios cocina</label>
            <input type="text" class="form-input" id="options-comments" placeholder="Escribir instrucciones especiales...">
        </div>
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
            <button class="btn btn-ghost" onclick="closeOptionsModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmOptions()">✓ Agregar al carrito</button>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
// Datos de opciones por producto (generados desde PHP)
const productOptionsData = @json($productOptions);

let cart = [];
let pendingProduct = null; // {id, name, price}

function formatCop(n) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n);
}

function filterCategory(catId, btn) {
    document.querySelectorAll('.category-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.product-card').forEach(card => {
        card.style.display = (catId === 'all' || card.dataset.category === catId) ? '' : 'none';
    });
}

function onProductClick(id, name, price) {
    const options = productOptionsData[id] || {};
    const hasOptions = Object.keys(options).length > 0;

    if (!hasOptions) {
        addToCart(id, name, price, [], '');
        return;
    }

    // Abrir modal de opciones
    pendingProduct = { id, name, price };
    document.getElementById('options-product-name').textContent = '🍽️ ' + name;
    document.getElementById('options-comments').value = '';

    const container = document.getElementById('options-container');
    let html = '';
    for (const [group, opts] of Object.entries(options)) {
        const isSoup = group.toLowerCase() === 'sopa';
        html += `<div class="opt-group-label">${isSoup ? '🍲' : group.toLowerCase().startsWith('sin') ? '❌' : group.toLowerCase().startsWith('extra') ? '➕' : '🔧'} ${erpEscape(group)}</div>`;
        html += `<div class="opt-chips" data-group="${erpEscape(group)}" data-mode="${isSoup ? 'radio' : 'check'}">`;
        for (const opt of opts) {
            html += `<label class="opt-chip ${isSoup ? 'radio-mode' : ''}" data-id="${erpEscape(opt.id)}" data-group="${erpEscape(group)}" onclick="toggleOpt(this, '${isSoup ? 'radio' : 'check'}')">
                ${erpEscape(opt.name)}
            </label>`;
        }
        html += `</div>`;
    }
    container.innerHTML = html;
    document.getElementById('options-overlay').classList.add('show');
}

function toggleOpt(chip, mode) {
    if (mode === 'radio') {
        // Solo uno por grupo
        const group = chip.dataset.group;
        document.querySelectorAll('.opt-chip').forEach(c => { if (c.dataset.group === group) c.classList.remove('active'); });
        chip.classList.add('active');
    } else {
        chip.classList.toggle('active');
    }
}

function closeOptionsModal() {
    document.getElementById('options-overlay').classList.remove('show');
    pendingProduct = null;
}

function confirmOptions() {
    if (!pendingProduct) return;
    const selectedOptions = [];
    document.querySelectorAll('.opt-chip.active').forEach(chip => {
        selectedOptions.push({ modifier_id: chip.dataset.id, name: chip.textContent.trim() });
    });
    const comments = document.getElementById('options-comments').value.trim();
    addToCart(pendingProduct.id, pendingProduct.name, pendingProduct.price, selectedOptions, comments);
    closeOptionsModal();
}

function addToCart(id, name, price, options, comments) {
    cart.push({ product_id: id, name, price, quantity: 1, comments: comments || '', modifiers: options || [] });
    renderCart();
}

function removeFromCart(index) { cart.splice(index, 1); renderCart(); }
function changeQty(index, delta) {
    cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    if (cart.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem;text-align:center;padding:2rem 0;">Agrega platos del menú</p>';
        document.getElementById('cart-total').textContent = formatCop(0);
        return;
    }
    let html = '';
    let total = 0;
    cart.forEach((item, i) => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        let optionsHtml = '';
        if (item.modifiers.length > 0) {
            optionsHtml = '<div class="cart-options">' + item.modifiers.map(m => `<span class="cart-option-tag">${erpEscape(m.name)}</span>`).join('') + '</div>';
        }
        html += `<div class="cart-item">
            <div style="flex:1;">
                <div style="font-weight:600;font-size:0.85rem;">${erpEscape(item.name)}</div>
                <div style="font-size:0.75rem;color:var(--text-muted);">${formatCop(item.price)} c/u</div>
                ${optionsHtml}
                ${item.comments ? `<div style="font-size:0.7rem;color:var(--warning);margin-top:0.2rem;">💬 ${erpEscape(item.comments)}</div>` : ''}
                <input type="text" class="form-input" style="margin-top:0.4rem;padding:0.3rem 0.5rem;font-size:0.75rem;" placeholder="Comentarios cocina..." value="${erpEscape(item.comments)}" onchange="cart[${i}].comments=this.value;">
            </div>
            <div class="cart-item-qty">
                <button class="qty-btn" onclick="changeQty(${i},-1)">−</button>
                <span style="font-weight:700;min-width:1.5rem;text-align:center;">${item.quantity}</span>
                <button class="qty-btn" onclick="changeQty(${i},1)">+</button>
            </div>
            <div style="text-align:right;min-width:60px;">
                <div style="font-weight:700;font-size:0.85rem;">${formatCop(subtotal)}</div>
                <button onclick="removeFromCart(${i})" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.75rem;">Quitar</button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
    document.getElementById('cart-total').textContent = formatCop(total);
}

function submitOrder() {
    if (cart.length === 0) { alert('Agrega al menos un plato.'); return; }
    const form = document.createElement('form');
    form.method = 'POST'; form.action = @json($storeRoute ?? route("waiter.store-order"));
    const field = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); };
    field('_token', @json(csrf_token()));
    field('restaurant_table_id', document.getElementById('table-select').value);
    cart.forEach((item, i) => {
        field(`items[${i}][product_id]`, item.product_id);
        field(`items[${i}][quantity]`, item.quantity);
        // Combinar opciones seleccionadas con comentarios
        let fullComments = item.modifiers.map(m => m.name).join(', ');
        if (item.comments) fullComments += (fullComments ? ' | ' : '') + item.comments;
        field(`items[${i}][comments]`, fullComments);
        // Enviar modificadores como IDs
        item.modifiers.forEach((mod, j) => {
            field(`items[${i}][modifiers][${j}][modifier_id]`, mod.modifier_id);
            field(`items[${i}][modifiers][${j}][quantity]`, 1);
        });
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
