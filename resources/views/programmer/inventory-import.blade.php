@extends('layouts.app')
@section('title', 'Importar Inventario CSV')
@push('styles')
<style>
    .import-box { border: 2px dashed var(--border); border-radius: var(--radius); padding: 2rem; text-align: center; background: var(--bg-input); }
    .import-box input[type=file] { margin-top: 1rem; width: 100%; max-width: 420px; }
    .error-list { max-height: 280px; overflow-y: auto; font-size: 0.8rem; margin-top: 0.75rem; }
    .error-list li { padding: 0.25rem 0; border-bottom: 1px solid var(--border); color: var(--warning); }
    .cols-table { font-size: 0.75rem; width: 100%; }
    .cols-table th, .cols-table td { padding: 0.35rem 0.5rem; text-align: left; border-bottom: 1px solid var(--border); }
    .upload-actions { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:1rem; }
    .file-actions { display:inline-flex; gap:0.35rem; flex-wrap:wrap; align-items:center; }
</style>
@endpush
@section('content')
<section class="page-header">
    <section>
        <h1 class="page-title">📥 Importar inventario comercial</h1>
        <p class="page-subtitle">Solo programador — carga masiva desde CSV (Excel)</p>
    </section>
    <section style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="{{ route('programmer.inventory-import.template') }}" class="btn btn-ghost">⬇️ Descargar plantilla CSV</a>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-ghost">Ver inventario</a>
        <a href="{{ route('programmer.panel') }}" class="btn btn-ghost">← Panel técnico</a>
    </section>
</section>

<section class="card" style="margin-bottom:1.5rem;">
    <h2 class="card-title" style="margin-bottom:1rem;">Seleccionar archivo en tu PC</h2>
    <form method="POST" enctype="multipart/form-data" action="{{ route('programmer.inventory-import.upload') }}">
        @csrf
        <section class="import-box">
            <p style="font-size:2.5rem;margin-bottom:0.5rem;">📄</p>
            <p style="color:var(--text-secondary);font-size:0.9rem;">
                Archivo <strong>.csv</strong> exportado desde Excel<br>
                <span style="font-size:0.75rem;color:var(--text-muted);">Máximo 10 MB · separador coma o punto y coma</span>
            </p>
            <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required class="form-input">
        </section>

        <label style="display:flex;align-items:center;gap:0.5rem;margin:1rem 0;font-size:0.85rem;">
            <input type="checkbox" name="update_existing" value="1" checked>
            Actualizar productos si el código ya existe (al importar)
        </label>

        <div class="upload-actions">
            <button type="submit" class="btn btn-ghost">
                📁 Solo cargar archivo
            </button>
            <button type="submit" class="btn btn-primary"
                formaction="{{ route('programmer.inventory-import.store') }}"
                onclick="return confirm('¿Cargar e importar productos al inventario?')">
                Importar ahora
            </button>
        </div>
    </form>

    @if(session('import_preview'))
    <section style="margin-top:1.25rem;">
        <h3 style="font-size:0.9rem;font-weight:700;color:var(--warning);">Detalle de advertencias</h3>
        <ul class="error-list">
            @foreach(session('import_preview') as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
        @if(count(session('import_errors', [])) > 15)
        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.5rem;">
            … y {{ count(session('import_errors')) - 15 }} más.
        </p>
        @endif
    </section>
    @endif
</section>

<section class="card" style="margin-bottom:1.5rem;">
    <h2 class="card-title" style="margin-bottom:1rem;">Archivos CSV cargados</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Fecha de carga</th>
                <th>Tipo</th>
                <th>Tamaño</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($uploads as $u)
            <tr>
                <td style="font-weight:600;max-width:220px;word-break:break-all;">{{ $u->original_name }}</td>
                <td style="font-size:0.8rem;">
                    {{ $u->created_at->format('d/m/Y') }}<br>
                    <span style="color:var(--text-muted);">{{ $u->created_at->format('H:i') }}</span>
                </td>
                <td><span class="badge badge-blue">{{ $u->type_label }}</span></td>
                <td>{{ $u->human_size }}</td>
                <td>
                    @if($u->isImported())
                        <span class="badge badge-green">{{ $u->status_label }}</span>
                        @if($u->import_summary)
                        <span style="font-size:0.65rem;color:var(--text-muted);display:block;margin-top:0.2rem;">
                            +{{ $u->import_summary['created'] ?? 0 }} / ~{{ $u->import_summary['updated'] ?? 0 }}
                        </span>
                        @endif
                    @else
                        <span class="badge badge-yellow">Pendiente</span>
                    @endif
                </td>
                <td>
                    <div class="file-actions">
                        <form method="POST" action="{{ route('programmer.inventory-import.import-stored', $u) }}">
                            @csrf
                            <input type="hidden" name="update_existing" value="1">
                            <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('¿Importar «{{ $u->original_name }}» al inventario?')">
                                {{ $u->isImported() ? '↻ Reimportar' : '▶ Importar' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('programmer.inventory-import.destroy', $u) }}"
                              onsubmit="return confirm('¿Eliminar el archivo «{{ $u->original_name }}» del servidor?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar archivo">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">
                    No hay archivos cargados. Usa el formulario de arriba para subir un CSV.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($uploads->hasPages())
    <section style="margin-top:1rem;">{{ $uploads->links() }}</section>
    @endif
</section>

<section class="grid grid-2" style="align-items:start;">
    <section class="card">
        <h2 class="card-title" style="margin-bottom:0.75rem;">Columnas del CSV</h2>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">
            La primera fila debe ser encabezado. Puedes usar estos nombres (mayúsculas o minúsculas):
        </p>
        <table class="cols-table data-table">
            <thead><tr><th>Columna</th><th>Obligatorio</th><th>Ejemplo</th></tr></thead>
            <tbody>
                <tr><td>PUNTO</td><td>Sí*</td><td>El Muelle, Bocagrande, Oficinas, Bodega</td></tr>
                <tr><td>UBICACION</td><td>No</td><td>Estante B2</td></tr>
                <tr><td>No. DEPARTAMENTO</td><td>No</td><td>1 a 8</td></tr>
                <tr><td>FAMILIA</td><td>Sí</td><td>Proteína, Verduras…</td></tr>
                <tr><td>CODIGO</td><td>Sí</td><td>PROD-001</td></tr>
                <tr><td>ARTICULO</td><td>Sí**</td><td>Nombre del producto (también NOMBRE)</td></tr>
                <tr><td>P.V.P</td><td>Sí</td><td>15000 (COP)</td></tr>
                <tr><td>STOCK</td><td>No</td><td>25</td></tr>
                <tr><td>UNIDAD</td><td>No</td><td>unit, gram, milliliter</td></tr>
                <tr><td>COSTO</td><td>No</td><td>12000</td></tr>
            </tbody>
        </table>
        <p style="font-size:0.7rem;color:var(--text-muted);margin-top:0.75rem;">
            * Si PUNTO está vacío se usa «El Muelle».<br>
            ** Si falta ARTICULO/NOMBRE se usa el CODIGO como nombre.
        </p>
    </section>

    <section class="card">
        <h3 style="font-size:0.85rem;font-weight:700;margin-bottom:0.5rem;">Departamentos (1-8)</h3>
        <ul style="font-size:0.75rem;color:var(--text-secondary);line-height:1.5;padding-left:1rem;">
            @foreach($departments as $num => $label)
            <li>{{ $num }} — {{ $label }}</li>
            @endforeach
        </ul>

        <h3 style="font-size:0.85rem;font-weight:700;margin:1rem 0 0.5rem;">Familias válidas</h3>
        <p style="font-size:0.75rem;color:var(--text-secondary);">
            {{ implode(' · ', $families) }}
        </p>
    </section>
</section>
@endsection
