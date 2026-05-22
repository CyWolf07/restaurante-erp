#Requires -Version 5.1
<#
.SYNOPSIS
    Instalador del ERP Restaurante en Windows (PC servidor de caja).
.DESCRIPTION
    Copia el proyecto, configura .env, instala dependencias, migra BD,
    crea accesos directos y regla de firewall para el puerto 8080.
#>
param(
    [string]$InstallDir = "",
    [int]$Port = 8080,
    [switch]$SkipCopy,
    [switch]$Silent
)

$ErrorActionPreference = "Stop"
$DesktopDir = $PSScriptRoot
$SourceRoot = (Resolve-Path (Join-Path $DesktopDir "..")).Path

if (-not $InstallDir) {
    $default = "C:\RestauranteERP"
    if (-not $Silent) {
        $input = Read-Host "Carpeta de instalacion [$default]"
        $InstallDir = if ([string]::IsNullOrWhiteSpace($input)) { $default } else { $input.Trim() }
    } else {
        $InstallDir = $default
    }
}

$InstallDir = [IO.Path]::GetFullPath($InstallDir)

function Write-Step([string]$msg) {
    Write-Host ""
    Write-Host "==> $msg" -ForegroundColor Cyan
}

function Test-Command([string]$name) {
    return [bool](Get-Command $name -ErrorAction SilentlyContinue)
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

function Get-EnvValue([string]$key, [string]$default = "") {
    $envPath = Join-Path (Get-Location) ".env"
    if (-not (Test-Path $envPath)) { return $default }

    $match = Select-String -Path $envPath -Pattern "^$([regex]::Escape($key))=(.*)$" | Select-Object -First 1
    if (-not $match) { return $default }

    return $match.Matches[0].Groups[1].Value.Trim().Trim('"')
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Yellow
Write-Host "  INSTALADOR ERP RESTAURANTE" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor Yellow
Write-Host "Origen:  $SourceRoot"
Write-Host "Destino: $InstallDir"
Write-Host ""

# --- Requisitos ---
Write-Step "Comprobando requisitos..."

if (-not (Test-Command php)) {
    Write-Host "ERROR: PHP no esta en el PATH." -ForegroundColor Red
    Write-Host "Instala PHP 8.2+ (Laragon, XAMPP o php.net) y vuelve a ejecutar."
    if (-not $Silent) { Read-Host "Enter para salir" }
    exit 1
}

$phpVer = (php -r "echo PHP_VERSION;")
Write-Host "  PHP: $phpVer" -ForegroundColor Green

if (-not (Test-Command composer)) {
    Write-Host "AVISO: Composer no encontrado. Se usara vendor incluido si existe." -ForegroundColor Yellow
} else {
    Write-Host "  Composer: OK" -ForegroundColor Green
}

# --- Copiar archivos ---
if (-not $SkipCopy) {
    Write-Step "Copiando archivos del sistema..."
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

    $exclude = @(
            "node_modules", ".git", "storage\logs\*",
        "storage\framework\cache\*", "storage\framework\sessions\*",
        "storage\framework\views\*", "bootstrap\cache\*",
        "desktop\RestauranteERP.exe", "desktop\Instalar-RestauranteERP.exe",
        "desktop\Output"
    )

    if (Test-Command robocopy) {
        robocopy $SourceRoot $InstallDir /E /NFL /NDL /NJH /NJS /NC /NS /NP `
            /XD node_modules .git desktop\Output `
            | Out-Null
        if ($LASTEXITCODE -ge 8) {
            throw "Robocopy fallo con codigo $LASTEXITCODE"
        }
    } else {
        Copy-Item -Path (Join-Path $SourceRoot "*") -Destination $InstallDir -Recurse -Force
    }
    Write-Host "  Copia completada." -ForegroundColor Green
} else {
    Write-Host "  Omitiendo copia (SkipCopy)." -ForegroundColor Yellow
}

Set-Location $InstallDir

# --- .env ---
Write-Step "Configurando entorno (.env)..."
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Set-EnvValue "APP_NAME" '"ERP Restaurante"'
        Set-EnvValue "APP_ENV" "production"
        Set-EnvValue "APP_DEBUG" "false"
        Set-EnvValue "APP_URL" "http://127.0.0.1:$Port"
        Set-EnvValue "APP_LOCALE" "es"
        Set-EnvValue "APP_FALLBACK_LOCALE" "es"
        Set-EnvValue "APP_FAKER_LOCALE" "es_ES"
        Set-EnvValue "SERVER_HOST" "0.0.0.0"
        Set-EnvValue "SERVER_PORT" "$Port"
        php artisan key:generate --force 2>&1 | Out-Null
        Write-Host "  .env creado desde .env.example" -ForegroundColor Green
        Write-Host ""
        Write-Host "  Base de datos por defecto: SQLite local." -ForegroundColor Green
        Write-Host "  Si usaras PostgreSQL, edita $InstallDir\.env y configura DB_CONNECTION=pgsql." -ForegroundColor Yellow
        if (-not $Silent) {
            $open = Read-Host "Abrir .env ahora? (S/N)"
            if ($open -match '^[sS]') { notepad (Join-Path $InstallDir ".env") }
        }
    } else {
        Write-Host "  AVISO: No hay .env.example" -ForegroundColor Yellow
    }
} else {
    Write-Host "  .env ya existe." -ForegroundColor Green
    Set-EnvValue "APP_URL" "http://127.0.0.1:$Port"
    Set-EnvValue "SERVER_HOST" "0.0.0.0"
    Set-EnvValue "SERVER_PORT" "$Port"
}

# --- Composer ---
if (Test-Path "vendor\autoload.php") {
    Write-Step "Dependencias PHP incluidas en vendor."
    Write-Host "  vendor/autoload.php OK." -ForegroundColor Green
} elseif (Test-Command composer) {
    Write-Step "Instalando dependencias PHP (composer)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "AVISO: composer install reporto errores. Revisa la conexion." -ForegroundColor Yellow
    }
} else {
    Write-Host "ERROR: No existe vendor\autoload.php y Composer no esta instalado." -ForegroundColor Red
    Write-Host "Solucion: genera el instalador desde una carpeta con 'composer install' ya ejecutado, o instala Composer en este equipo."
    if (-not $Silent) { Read-Host "Enter para salir" }
    exit 1
}

# --- SQLite local ---
$dbConnection = Get-EnvValue "DB_CONNECTION" "sqlite"
if ($dbConnection -eq "sqlite") {
    Write-Step "Preparando base de datos SQLite..."
    New-Item -ItemType Directory -Force -Path "database" | Out-Null
    $sqlitePath = Join-Path (Get-Location) "database\database.sqlite"
    if (-not (Test-Path $sqlitePath)) {
        New-Item -ItemType File -Path $sqlitePath -Force | Out-Null
        Write-Host "  Creado: $sqlitePath" -ForegroundColor Green
    } else {
        Write-Host "  SQLite existente: $sqlitePath" -ForegroundColor Green
    }
}

# --- Permisos storage ---
Write-Step "Permisos de carpetas storage y bootstrap/cache..."
$dirs = @("storage", "bootstrap\cache")
foreach ($d in $dirs) {
    if (Test-Path $d) {
        icacls $d /grant "Users:(OI)(CI)M" /T 2>$null | Out-Null
    }
}

# --- Migraciones ---
Write-Step "Migrando base de datos..."
php artisan migrate --force 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "AVISO: migrate fallo. Verifica PostgreSQL y .env antes de iniciar." -ForegroundColor Yellow
} else {
    Write-Host "  Migraciones OK." -ForegroundColor Green
}

# --- Semilla opcional ---
if (-not $Silent) {
    $seed = Read-Host "Ejecutar datos de demostracion (php artisan db:seed)? (S/N)"
    if ($seed -match '^[sS]') {
        php artisan db:seed --force
    }
}

# --- Firewall ---
Write-Step "Regla de firewall (puerto $Port)..."
$ruleName = "RestauranteERP-HTTP-$Port"
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if (-not $existing) {
    try {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Private,Domain | Out-Null
        Write-Host "  Regla creada: $ruleName" -ForegroundColor Green
    } catch {
        Write-Host "  No se pudo crear regla (ejecuta como Administrador)." -ForegroundColor Yellow
    }
} else {
    Write-Host "  Regla ya existente." -ForegroundColor Green
}

# --- Accesos directos ---
Write-Step "Creando accesos directos..."
$WshShell = New-Object -ComObject WScript.Shell
$launcherBat = Join-Path $DesktopDir "Iniciar-RestauranteERP.bat"

# Ajustar launcher en destino
$destLauncher = Join-Path $InstallDir "desktop\Iniciar-RestauranteERP.bat"
$destDesktop = [Environment]::GetFolderPath("Desktop")
$programs = [Environment]::GetFolderPath("Programs")

$shortcutStart = $WshShell.CreateShortcut((Join-Path $programs "Restaurante ERP.lnk"))
$shortcutStart.TargetPath = $destLauncher
$shortcutStart.WorkingDirectory = $InstallDir
$shortcutStart.Description = "Iniciar servidor ERP Restaurante (LAN)"
$shortcutStart.Save()

$shortcutDesk = $WshShell.CreateShortcut((Join-Path $destDesktop "Restaurante ERP.lnk"))
$shortcutDesk.TargetPath = $destLauncher
$shortcutDesk.WorkingDirectory = $InstallDir
$shortcutDesk.Description = "Iniciar servidor ERP Restaurante"
$shortcutDesk.Save()

Write-Host "  Menu Inicio y Escritorio." -ForegroundColor Green

# --- Archivo de ruta instalada ---
$infoFile = Join-Path $InstallDir "INSTALADO.txt"
@"
ERP Restaurante instalado: $(Get-Date -Format "yyyy-MM-dd HH:mm")
Ruta: $InstallDir
URL local: http://127.0.0.1:$Port/login
Tablets (misma WiFi): http://TU-IP-LAN:$Port
"@ | Set-Content -Path $infoFile -Encoding UTF8

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  INSTALACION COMPLETADA" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Carpeta: $InstallDir"
Write-Host "Iniciar: doble clic en 'Restaurante ERP' (escritorio)"
Write-Host "O: $destLauncher"
Write-Host ""

if (-not $Silent) {
    $run = Read-Host "Iniciar el servidor ahora? (S/N)"
    if ($run -match '^[sS]') {
        Set-Location $InstallDir
        & $destLauncher
    }
}
