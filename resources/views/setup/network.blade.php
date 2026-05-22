@extends('layouts.app')
@section('title', 'Conexión Tablets — Red LAN')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📡 Conexión en Red Local</h1>
        <p class="page-subtitle">Usa estas URLs en tablets y terminales (misma Wi‑Fi / LAN)</p>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">URLs para tablets</h2>
        <p style="color:var(--text-muted);font-size:0.85rem;margin:0.75rem 0 1rem;">
            Servidor escuchando en <code>{{ $lan['host'] }}:{{ $lan['port'] }}</code>
        </p>
        @foreach($lan['tablet_urls'] as $url)
        <div style="background:var(--bg-input);padding:1rem;border-radius:var(--radius-sm);margin-bottom:0.75rem;border:1px solid var(--border);">
            <div style="font-size:1.1rem;font-weight:700;word-break:break-all;">{{ $url }}</div>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:0.5rem;" onclick="navigator.clipboard.writeText('{{ $url }}')">📋 Copiar</button>
            <a href="{{ $url }}" target="_blank" class="btn btn-primary btn-sm" style="margin-left:0.25rem;">Abrir</a>
        </div>
        @endforeach
        <p style="font-size:0.8rem;color:var(--text-muted);">
            En cada tablet: abre Chrome/Safari → pega la URL → inicia sesión con PIN del mesero o cajero.
            Opcional: «Añadir a pantalla de inicio» para modo app.
        </p>
    </div>

    <div class="card">
        <h2 class="card-title">PC servidor (.exe)</h2>
        <ol style="font-size:0.85rem;color:var(--text-secondary);line-height:1.9;padding-left:1.25rem;margin-top:1rem;">
            <li>Ejecuta <strong>RestauranteERP.exe</strong> en el PC de caja (carpeta <code>desktop/</code>).</li>
            <li>Base de datos: SQLite local por defecto, o PostgreSQL si lo configuraste en <code>.env</code>.</li>
            <li>El firewall de Windows debe permitir el puerto <strong>{{ $lan['port'] }}</strong>.</li>
            <li>Las tablets deben estar en la misma red que este PC.</li>
        </ol>
        <div class="alert alert-warning" style="margin-top:1rem;">
            ⚠️ No requiere internet. Todo funciona 100% en LAN local.
        </div>
    </div>
</div>
@endsection
