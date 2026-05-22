<form method="POST" action="{{ route('admin.inventory.update', $supply) }}" class="inv-edit-form">
    @csrf
    @method('PUT')
    <input type="hidden" name="tab" value="inventario">

    <div class="grid grid-3" style="gap:0.5rem;">
        <div class="form-group">
            <label class="form-label">Punto *</label>
            <select name="point" class="form-select" required>
                @foreach($points as $k => $label)
                <option value="{{ $k }}" @selected($supply->point === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Ubicación</label>
            <input type="text" name="location" class="form-input" value="{{ $supply->location }}">
        </div>
        <div class="form-group">
            <label class="form-label">Departamento</label>
            <select name="department_number" class="form-select">
                <option value="">— Sin asignar —</option>
                @foreach($departments as $num => $deptLabel)
                <option value="{{ $num }}" @selected($supply->department_number == $num)>{{ $num }} — {{ $deptLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Familia *</label>
            <select name="family" class="form-select" required>
                @foreach($families as $k => $label)
                <option value="{{ $k }}" @selected($supply->family === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Código *</label>
            <input type="text" name="code" class="form-input" value="{{ $supply->code }}" required>
        </div>
        <div class="form-group">
            <label class="form-label">P.V.P (COP) *</label>
            <input type="number" step="1" name="pvp" class="form-input" value="{{ (int) $supply->pvp }}" required>
        </div>
    </div>

    <div class="grid grid-2" style="gap:0.5rem;margin-top:0.5rem;">
        <div class="form-group">
            <label class="form-label">Nombre corto *</label>
            <input type="text" name="name" class="form-input" value="{{ $supply->name }}" required>
        </div>
        <div class="form-group">
            <label class="form-label">Proveedor</label>
            <input type="text" name="supplier" class="form-input" value="{{ $supply->supplier }}">
        </div>
    </div>

    <div class="grid grid-4" style="gap:0.5rem;margin-top:0.5rem;">
        <div class="form-group">
            <label class="form-label">Unidad</label>
            <select name="unit_type" class="form-select">
                <option value="unit" @selected($supply->unit_type === 'unit')>Unidad</option>
                <option value="gram" @selected($supply->unit_type === 'gram')>Gramos</option>
                <option value="milliliter" @selected($supply->unit_type === 'milliliter')>Mililitros</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Inventario físico</label>
            <input type="number" step="0.001" name="physical_count" class="form-input"
                   value="{{ $supply->physical_count ?? 0 }}" title="Conteo manual (columna Inventario)">
        </div>
        <div class="form-group">
            <label class="form-label">Stock sistema</label>
            <input type="number" step="0.0001" name="current_stock" class="form-input" value="{{ $supply->current_stock }}">
        </div>
        <div class="form-group">
            <label class="form-label">Stock mínimo</label>
            <input type="number" step="0.0001" name="min_stock" class="form-input" value="{{ $supply->min_stock }}">
        </div>
        <div class="form-group">
            <label class="form-label">Costo unit. (COP)</label>
            <input type="number" step="1" name="cost_per_unit" class="form-input" value="{{ (int) $supply->cost_per_unit }}">
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.75rem;flex-wrap:wrap;gap:0.5rem;">
        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;">
            <input type="checkbox" name="active" value="1" @checked($supply->active)> Producto activo
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
    </div>
</form>
