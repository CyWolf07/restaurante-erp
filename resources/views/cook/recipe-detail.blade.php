@extends('layouts.app')
@section('title', $product->name . ' — Ficha Técnica')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📋 {{ $product->name }}</h1>
        <p class="page-subtitle">{{ $product->category->name ?? 'Sin categoría' }} — Solo lectura</p>
    </div>
    <a href="{{ route('cook.recipes') }}" class="btn btn-ghost">← Volver</a>
</div>

<div class="grid grid-2">
    <div class="card">
        @if($product->image_path)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:100%;border-radius:var(--radius-sm);">
        @else
            <div style="height:200px;display:flex;align-items:center;justify-content:center;font-size:4rem;background:var(--bg-input);border-radius:var(--radius-sm);">🍽️</div>
        @endif
        <p style="margin-top:1rem;color:var(--text-secondary);">{{ $product->description }}</p>
        <p style="margin-top:0.5rem;">⏱️ {{ $product->preparation_time }} minutos</p>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:1rem;">🥘 Ingredientes</h2>
        <table class="data-table">
            <thead><tr><th>Insumo</th><th>Cantidad</th><th>Unidad</th></tr></thead>
            <tbody>
                @foreach($product->recipes as $recipe)
                <tr>
                    <td>{{ $recipe->supply->name ?? '—' }}</td>
                    <td>{{ number_format($recipe->quantity_required, 2) }}</td>
                    <td>{{ $recipe->supply->unit_label ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($product->recipe_instructions)
        <h2 class="card-title" style="margin:1.5rem 0 1rem;">👨‍🍳 Preparación</h2>
        <div style="white-space:pre-line;font-size:0.9rem;line-height:1.8;">{{ $product->recipe_instructions }}</div>
        @endif
    </div>
</div>
@endsection
