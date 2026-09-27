<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ERP Restaurante') }}</title>
    <meta http-equiv="refresh" content="0;url={{ route('login') }}">
</head>
<body>
    <p>Redirigiendo al inicio de sesion...</p>
    <p><a href="{{ route('login') }}">Entrar al sistema</a></p>
</body>
</html>
