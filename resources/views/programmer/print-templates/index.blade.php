@extends('layouts.app')
@section('title', 'Formatos de impresión')
@section('content')
<section class="page-header">
    <section>
        <h1 class="page-title">🖨️ Formatos de impresión</h1>
        <p class="page-subtitle">Define qué muestra cada ticket térmico (pre-ticket, cocina, cobro)</p>
    </section>
    <a href="{{ route('programmer.panel') }}" class="btn btn-ghost">← Panel técnico</a>
</section>

<section class="grid grid-3">
    @foreach($templates as $template)
    <article class="card">
        <h2 class="card-title">{{ $types[$template->slug] ?? $template->name }}</h2>
        <p style="font-size:0.85rem;color:var(--text-muted);margin:0.75rem 0 1rem;">
            Slug: <code>{{ $template->slug }}</code><br>
            @if($template->updated_at)
            Actualizado: {{ $template->updated_at->format('d/m/Y H:i') }}
            @endif
        </p>
        <a href="{{ route('programmer.print-templates.edit', $template->slug) }}" class="btn btn-primary">Editar formato</a>
    </article>
    @endforeach
</section>

<section class="card" style="margin-top:1.5rem;">
    <h3 class="card-title">Placeholders disponibles</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);">
        En título, subtítulo y líneas extra puedes usar:
        @foreach(config('ticket_print.placeholders.preticket', []) as $token => $desc)
            <code>{{ $token }}</code>@if(!$loop->last), @endif
        @endforeach
    </p>
</section>
@endsection
