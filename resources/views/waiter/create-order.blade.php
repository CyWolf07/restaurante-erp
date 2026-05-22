@extends('layouts.app')
@section('title', 'Nueva Orden — Comandero Digital')
@push('styles')
<style>
    .commander { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; min-height: calc(100vh - 8rem); }
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
            <div class="product-card" data-category="{{ $cat->id }}" onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', {{ $product->price }})">
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
                <label class="form-label">Mesa</label>
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

{{-- Modifiers data --}}
<script>const modifiersData = @json($modifiers);</script>
@endsection
@push('scripts')
<script>
let cart = [];

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

function addToCart(id, name, price) {
    const existing = cart.find(i => i.product_id === id);
    if (existing) { existing.quantity++; }
    else { cart.push({ product_id: id, name, price, quantity: 1, comments: '', modifiers: [] }); }
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
        html += `<div class="cart-item">
            <div style="flex:1;">
                <div style="font-weight:600;font-size:0.85rem;">${item.name}</div>
                <div style="font-size:0.75rem;color:var(--text-muted);">${formatCop(item.price)} c/u</div>
                <input type="text" class="form-input" style="margin-top:0.4rem;padding:0.3rem 0.5rem;font-size:0.75rem;" placeholder="Comentarios cocina..." value="${item.comments}" onchange="cart[${i}].comments=this.value;">
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
    form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
    form.innerHTML += `<input type="hidden" name="restaurant_table_id" value="${document.getElementById('table-select').value}">`;
    cart.forEach((item, i) => {
        form.innerHTML += `<input type="hidden" name="items[${i}][product_id]" value="${item.product_id}">`;
        form.innerHTML += `<input type="hidden" name="items[${i}][quantity]" value="${item.quantity}">`;
        form.innerHTML += `<input type="hidden" name="items[${i}][comments]" value="${item.comments}">`;
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
