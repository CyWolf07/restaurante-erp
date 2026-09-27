@extends('layouts.app')
@section('title', 'Platos del Menú')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🍽️ Platos del Menú</h1>
        <p class="page-subtitle">Alta de platos con ficha técnica (receta / BOM)</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.products') }}" class="card" style="margin-bottom:1rem;">
    <label for="product-search" class="form-label">Buscar plato</label>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <input id="product-search" name="q" value="{{ request('q') }}" maxlength="100" class="form-input" placeholder="Nombre del plato">
        <button class="btn btn-primary" type="submit">Buscar</button>
        <a class="btn btn-ghost" href="{{ route('admin.products') }}">Ver todos</a>
    </div>
</form>
<div class="card" style="margin-bottom:1.5rem;" id="product-form-card">
    <h2 class="card-title" style="margin-bottom:1rem;">Nuevo plato</h2>
    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" id="product-form">
        @csrf
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Nombre *</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Categoría</label>
                <select name="category_id" class="form-select">
                    <option value="">Sin categoría</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Precio *</label>
                <input type="number" step="0.01" name="price" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Tiempo prep. (min)</label>
                <input type="number" name="preparation_time" class="form-input" value="10" min="0">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea name="description" class="form-textarea" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Instrucciones de cocción</label>
            <textarea name="recipe_instructions" class="form-textarea" rows="4" placeholder="Paso 1...&#10;Paso 2..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Imagen</label>
            <input type="file" name="image" class="form-input" accept="image/*">
        </div>

        <h3 style="font-size:0.9rem;font-weight:700;margin:1rem 0 0.5rem;">Ficha técnica (insumos)</h3>
        <div id="recipe-rows"></div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addRecipeRow()" style="margin-bottom:1rem;">+ Añadir insumo</button>

        <button type="submit" class="btn btn-primary">Guardar plato</button>
    </form>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>Plato</th><th>Categoría</th><th>Precio</th><th>Receta</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong></td>
                <td>{{ $product->category->name ?? '—' }}</td>
                <td>${{ number_format($product->price, 2) }}</td>
                <td style="font-size:0.75rem;">{{ $product->recipes->count() }} insumos</td>
                <td>
                    <span class="badge badge-{{ $product->active ? 'green' : 'gray' }}">
                        {{ $product->active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    <details>
                        <summary class="btn btn-ghost btn-sm">Editar</summary>
                        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data"
                              style="margin-top:0.75rem;padding:1rem;background:var(--bg-input);border-radius:var(--radius-sm);max-width:480px;">
                            @csrf @method('PUT')
                            <input type="hidden" name="replace_recipes" value="1">
                            <input type="text" name="name" class="form-input" value="{{ $product->name }}" required style="margin-bottom:0.5rem;">
                            <select name="category_id" class="form-select" style="margin-bottom:0.5rem;">
                                <option value="">Sin categoría</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($product->category_id==$cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" name="price" class="form-input" value="{{ $product->price }}" style="margin-bottom:0.5rem;">
                            <input type="number" name="preparation_time" class="form-input" value="{{ $product->preparation_time }}" style="margin-bottom:0.5rem;">
                            <textarea name="recipe_instructions" class="form-textarea" rows="3" style="margin-bottom:0.5rem;">{{ $product->recipe_instructions }}</textarea>
                            @foreach($product->recipes as $i => $recipe)
                            <div style="display:flex;gap:0.5rem;margin-bottom:0.35rem;">
                                <select name="recipes[{{ $i }}][supply_id]" class="form-select">
                                    @foreach($supplies as $s)
                                    <option value="{{ $s->id }}" @selected($recipe->supply_id==$s->id)>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.0001" name="recipes[{{ $i }}][quantity_required]" class="form-input" value="{{ $recipe->quantity_required }}" style="width:100px;">
                            </div>
                            @endforeach
                            <label style="font-size:0.8rem;display:flex;gap:0.5rem;margin:0.5rem 0;">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" name="active" value="1" {{ $product->active?'checked':'' }}> Activo
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                        </form>
                        @if($product->active)
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" style="margin-top:0.35rem;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                        </form>
                        @endif
                    </details>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:1rem;">{{ $products->links() }}</div>
@endsection
@push('scripts')
<script>
const supplies = @json($supplies->map(fn($s) => ['id'=>$s->id,'name'=>$s->name,'unit'=>$s->unit_label]));
let recipeIdx = 0;

function addRecipeRow() {
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;gap:0.5rem;margin-bottom:0.5rem;';

    div.innerHTML = `
        <select name="recipes[${recipeIdx}][supply_id]" class="form-select" required></select>
        <input type="number" step="0.0001" name="recipes[${recipeIdx}][quantity_required]" class="form-input" placeholder="Cant." style="width:120px;" required>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">×</button>`;
    const select = div.querySelector('select');
    supplies.forEach(s => select.add(new Option(`${s.name} (${s.unit})`, s.id)));
    document.getElementById('recipe-rows').appendChild(div);
    recipeIdx++;
}
addRecipeRow();
</script>
@endpush
