@extends('layouts.app')
@section('title', 'Gestión de Mesas')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🪑 Mesas del Restaurante</h1>
        <p class="page-subtitle">Crea mesas y asígnalas a impresoras de cocina o caja</p>
    </div>
    <a href="{{ route('admin.printers') }}" class="btn btn-ghost">🖨️ Impresoras</a>
</div>

<div class="grid grid-2" style="margin-bottom:1.5rem;">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">Nueva mesa</h2>
        <form method="POST" action="{{ route('admin.tables.store') }}">
            @csrf
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Número *</label>
                    <input type="number" name="number" class="form-input" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Capacidad</label>
                    <input type="number" name="capacity" class="form-input" value="4" min="1" max="50">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre (opcional)</label>
                <input type="text" name="name" class="form-input" placeholder="Ej. Terraza VIP">
            </div>
            <div class="form-group">
                <label class="form-label">Zona</label>
                <input type="text" name="zone" class="form-input" value="Salón" required>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Impresora cocina</label>
                    <select name="kitchen_printer_id" class="form-select">
                        <option value="">— Por defecto —</option>
                        @foreach($printers->where('purpose','kitchen') as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Impresora ticket</label>
                    <select name="receipt_printer_id" class="form-select">
                        <option value="">— Por defecto —</option>
                        @foreach($printers->where('purpose','receipt') as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Crear mesa</button>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>#</th><th>Nombre</th><th>Zona</th><th>Impresoras</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            @forelse($tables as $table)
            <tr>
                <td><strong>{{ $table->number }}</strong></td>
                <td>{{ $table->display_name }}</td>
                <td>{{ $table->zone }}</td>
                <td style="font-size:0.75rem;">
                    🍳 {{ $table->kitchenPrinter->name ?? 'default' }}<br>
                    🧾 {{ $table->receiptPrinter->name ?? 'default' }}
                </td>
                <td>
                    @if($table->active)
                        <span class="badge badge-green">Activa</span>
                    @else
                        <span class="badge badge-gray">Inactiva</span>
                    @endif
                </td>
                <td>
                    <details>
                        <summary class="btn btn-ghost btn-sm">Editar</summary>
                        <form method="POST" action="{{ route('admin.tables.update', $table) }}" style="margin-top:0.75rem;padding:0.75rem;background:var(--bg-input);border-radius:var(--radius-sm);">
                            @csrf @method('PUT')
                            <div class="grid grid-2" style="gap:0.5rem;">
                                <input type="number" name="number" class="form-input" value="{{ $table->number }}" required>
                                <input type="text" name="name" class="form-input" value="{{ $table->name }}" placeholder="Nombre">
                                <input type="text" name="zone" class="form-input" value="{{ $table->zone }}" required>
                                <input type="number" name="capacity" class="form-input" value="{{ $table->capacity }}">
                            </div>
                            <div class="grid grid-2" style="margin-top:0.5rem;">
                                <select name="kitchen_printer_id" class="form-select">
                                    <option value="">Cocina default</option>
                                    @foreach($printers->where('purpose','kitchen') as $p)
                                    <option value="{{ $p->id }}" @selected($table->kitchen_printer_id==$p->id)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <select name="receipt_printer_id" class="form-select">
                                    <option value="">Caja default</option>
                                    @foreach($printers->where('purpose','receipt') as $p)
                                    <option value="{{ $p->id }}" @selected($table->receipt_printer_id==$p->id)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label style="display:flex;align-items:center;gap:0.5rem;margin:0.5rem 0;font-size:0.8rem;">
                                <input type="checkbox" name="active" value="1" {{ $table->active?'checked':'' }}> Activa
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.tables.destroy', $table) }}" style="margin-top:0.5rem;" onsubmit="return confirm('¿Eliminar mesa?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </details>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No hay mesas. Crea la primera arriba.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
