#Requires -Version 5.1
<#
.SYNOPSIS
    Repara permisos y carpetas escribibles de una instalacion existente.
#>
param(
    [string]$InstallDir = "C:\Program Files\RestauranteERP",
    [int]$Port = 8080
)

$ErrorActionPreference = "Stop"

$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).
    IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "Solicitando permisos de administrador..."
    $args = "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`" -InstallDir `"$InstallDir`" -Port $Port"
    Start-Process -FilePath "powershell.exe" -ArgumentList $args -Verb RunAs
    exit
}

function Set-EnvValue([string]$key, [string]$value) {
    $envPath = Join-Path (Get-Location) ".env"
    if (-not (Test-Path $envPath)) { return }

    $escaped = [regex]::Escape($key)
    $line = "$key=$value"
    $content = Get-Content $envPath -Raw

    if ($content -match "(?m)^$escaped=") {
        $content = [regex]::Replace($content, "(?m)^$escaped=.*$", $line)
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }

    Set-Content -Path $envPath -Value $content -Encoding UTF8
}

if (-not (Test-Path $InstallDir)) {
    Write-Host "ERROR: No existe la carpeta: $InstallDir" -ForegroundColor Red
    Read-Host "Enter para salir"
    exit 1
}

Set-Location $InstallDir

Write-Host ""
Write-Host "Reparando Restaurante ERP en: $InstallDir" -ForegroundColor Cyan

$dirs = @(
    "database",
    "storage",
    "storage\logs",
    "storage\framework",
    "storage\framework\cache",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\framework\compiled_views",
    "storage\framework\testing",
    "bootstrap\cache"
)

foreach ($dir in $dirs) {
    New-Item -ItemType Directory -Force -Path (Join-Path $InstallDir $dir) | Out-Null
}

icacls $InstallDir /grant "*S-1-5-32-545:(OI)(CI)M" /T /C | Out-Null

if (-not (Test-Path ".env") -and (Test-Path ".env.example")) {
    Copy-Item ".env.example" ".env" -Force
    Set-EnvValue "APP_NAME" '"ERP Restaurante"'
    Set-EnvValue "APP_ENV" "production"
    Set-EnvValue "APP_DEBUG" "false"
    Set-EnvValue "APP_URL" "http://127.0.0.1:$Port"
    Set-EnvValue "APP_LOCALE" "es"
    Set-EnvValue "APP_FALLBACK_LOCALE" "es"
    Set-EnvValue "APP_FAKER_LOCALE" "es_ES"
    Set-EnvValue "SERVER_HOST" "0.0.0.0"
    Set-EnvValue "SERVER_PORT" "$Port"
}

if ((Test-Path ".env") -and (Test-Path "artisan")) {
    php artisan key:generate --force
    php artisan migrate --force
}

Write-Host ""
Write-Host "Reparacion completada." -ForegroundColor Green
Write-Host "Ahora inicia Restaurante ERP otra vez desde el acceso directo."
Read-Host "Enter para salir"
