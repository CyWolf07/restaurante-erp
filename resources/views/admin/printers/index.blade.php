@extends('layouts.app')
@section('title', 'Impresoras')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🖨️ Impresoras</h1>
        <p class="page-subtitle">Conecta impresoras térmicas por red (IP) o Windows</p>
    </div>
    <a href="{{ route('admin.tables') }}" class="btn btn-ghost">🪑 Mesas</a>
</div>

<div class="grid grid-2" style="margin-bottom:1.5rem;">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">Registrar impresora</h2>
        <form method="POST" action="{{ route('admin.printers.store') }}" x-data="{ conn: 'network' }">
            @csrf
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input type="text" name="name" class="form-input" placeholder="Cocina principal" required>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Uso</label>
                    <select name="purpose" class="form-select" required>
                        <option value="kitchen">Cocina (comandas)</option>
                        <option value="receipt">Caja (tickets)</option>
                        <option value="bar">Bar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Conexión</label>
                    <select name="connection_type" class="form-select" x-model="conn" required>
                        <option value="network">Red (IP)</option>
                        <option value="windows">Windows (nombre)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" x-text="conn === 'network' ? 'Dirección IP' : 'Nombre impresora Windows'"></label>
                @if(count($windowsPrinters) > 0)
                <datalist id="win-printers">
                    @foreach($windowsPrinters as $wp)
                    <option value="{{ $wp }}">
                    @endforeach
                </datalist>
                @endif
                <input type="text" name="address" class="form-input" list="win-printers"
                       :placeholder="conn === 'network' ? '192.168.1.100' : 'EPSON TM-T20'" required>
            </div>
            <div class="grid grid-2" x-show="conn === 'network'">
                <div class="form-group">
                    <label class="form-label">Puerto</label>
                    <input type="number" name="port" class="form-input" value="9100">
                </div>
                <div class="form-group">
                    <label class="form-label">Ancho papel (mm)</label>
                    <select name="paper_width" class="form-select">
                        <option value="80">80 mm</option>
                        <option value="58">58 mm</option>
                    </select>
                </div>
            </div>
            <div x-show="conn === 'windows'" style="display:none;">
                <input type="hidden" name="port" value="9100">
                <select name="paper_width" class="form-select" style="display:none;"><option value="80">80</option></select>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;margin:0.75rem 0;font-size:0.85rem;">
                <input type="checkbox" name="is_default" value="1"> Predeterminada para su uso
            </label>
            <button type="submit" class="btn btn-primary">Guardar impresora</button>
        </form>
    </div>
    <div class="card">
        <h2 class="card-title">Ayuda rápida</h2>
        <ul style="font-size:0.85rem;color:var(--text-secondary);margin-top:1rem;line-height:1.8;padding-left:1.25rem;">
            <li><strong>Red:</strong> IP de la impresora, puerto 9100 (RAW).</li>
            <li><strong>Windows:</strong> nombre exacto en Panel de control → Impresoras.</li>
            <li>Asigna impresoras por mesa en <a href="{{ route('admin.tables') }}">Gestión de mesas</a>.</li>
            <li>Usa <strong>Probar</strong> para verificar antes de operar.</li>
        </ul>
        @if(count($windowsPrinters))
        <p style="margin-top:1rem;font-size:0.8rem;color:var(--text-muted);">Detectadas: {{ implode(', ', $windowsPrinters) }}</p>
        @endif
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>Nombre</th><th>Uso</th><th>Conexión</th><th>Default</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @foreach($printers as $printer)
            <tr>
                <td><strong>{{ $printer->name }}</strong></td>
                <td>{{ $printer->purpose_label }}</td>
                <td style="font-size:0.8rem;">{{ $printer->connection_label }}</td>
                <td>@if($printer->is_default)<span class="badge badge-blue">Sí</span>@else—@endif</td>
                <td style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('admin.printers.test', $printer) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Probar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.printers.destroy', $printer) }}" onsubmit="return confirm('¿Eliminar?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
