@extends('layouts.app')
@section('title', 'Editar formato — ' . ($types[$template->slug] ?? $template->name))
@push('styles')
<style>
    .ticket-preview {
        font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
        font-size: 0.75rem;
        line-height: 1.35;
        background: #1a1a1a;
        color: #e8e8e8;
        padding: 1rem 1.25rem;
        border-radius: var(--radius);
        white-space: pre-wrap;
        max-width: 320px;
        margin: 0 auto;
    }
    .format-grid { display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem; align-items: start; }
    @media (max-width: 900px) { .format-grid { grid-template-columns: 1fr; } }
    .check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem 1rem; }
    .check-grid label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; }
</style>
@endpush
@section('content')
<section class="page-header">
    <section>
        <h1 class="page-title">🖨️ {{ $types[$template->slug] ?? $template->name }}</h1>
        <p class="page-subtitle">Configura el contenido del ticket antes de imprimir</p>
    </section>
    <section style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="{{ route('programmer.print-templates.index') }}" class="btn btn-ghost">← Todos los formatos</a>
        <a href="{{ route('admin.printers') }}" class="btn btn-ghost">Impresoras</a>
    </section>
</section>

<section class="format-grid">
    <form method="POST" action="{{ route('programmer.print-templates.update', $template->slug) }}" class="card">
        @csrf
        @method('PUT')

        <h3 class="card-title" style="margin-bottom:1rem;">Encabezado</h3>
        <div class="form-group">
            <label class="form-label">Título</label>
            <input type="text" name="title" class="form-input" value="{{ old('title', $settings['title'] ?? '') }}" maxlength="80">
        </div>
        <div class="form-group">
            <label class="form-label">Subtítulo</label>
            <input type="text" name="subtitle" class="form-input" value="{{ old('subtitle', $settings['subtitle'] ?? '') }}" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label">Líneas extra (una por línea)</label>
            <textarea name="extra_header_lines" class="form-input" rows="3">{{ old('extra_header_lines', implode("\n", $settings['extra_header_lines'] ?? [])) }}</textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Pie del ticket (una por línea)</label>
            <textarea name="footer_lines" class="form-input" rows="2">{{ old('footer_lines', implode("\n", $settings['footer_lines'] ?? [])) }}</textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Impresora a usar</label>
            <select name="printer_purpose" class="form-input" required>
                <option value="kitchen" @selected(old('printer_purpose', $settings['printer_purpose'] ?? '') === 'kitchen')>Cocina (comanda)</option>
                <option value="receipt" @selected(old('printer_purpose', $settings['printer_purpose'] ?? 'receipt') === 'receipt')>Caja / recibo</option>
            </select>
            <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.35rem;">
                Se resuelve por mesa o impresora predeterminada del admin.
            </p>
        </div>

        <h3 class="card-title" style="margin:1.5rem 0 1rem;">Qué mostrar</h3>
        <div class="check-grid">
            @foreach([
                'show_restaurant_name' => 'Nombre restaurante',
                'show_table' => 'Mesa',
                'table_large' => 'Mesa grande (cocina)',
                'show_waiter' => 'Mesero',
                'show_kitchen_sender' => 'Quién envió a cocina',
                'show_datetime' => 'Fecha y hora',
                'show_items' => 'Ítems del pedido',
                'show_item_subtotal' => 'Subtotal por ítem',
                'show_modifiers' => 'Modificadores',
                'show_item_comments' => 'Comentarios ítem',
                'show_subtotal' => 'Subtotal orden',
                'show_tax' => 'IVA',
                'show_total' => 'Total',
                'title_bold' => 'Título en negrita',
                'total_bold' => 'Total en negrita',
            ] as $key => $label)
            <label>
                <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $settings[$key] ?? false))>
                {{ $label }}
            </label>
            @endforeach
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary">Guardar formato</button>
        </div>
    </form>

    <aside class="card" style="position:sticky;top:1rem;">
        <h3 class="card-title" style="margin-bottom:0.75rem;">Vista previa (58/80 mm)</h3>
        <div class="ticket-preview">{{ session('preview', $previewText) }}</div>
        @if(!empty($placeholders))
        <details style="margin-top:1rem;font-size:0.8rem;">
            <summary style="cursor:pointer;color:var(--text-muted);">Variables disponibles</summary>
            <ul style="margin-top:0.5rem;padding-left:1.25rem;color:var(--text-muted);">
                @foreach($placeholders as $token => $desc)
                <li><code>{{ $token }}</code> — {{ $desc }}</li>
                @endforeach
            </ul>
        </details>
        @endif
    </aside>
</section>
@endsection
