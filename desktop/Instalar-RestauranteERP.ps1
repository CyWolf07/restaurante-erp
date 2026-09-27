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

function Write-Warn([string]$msg) {
    Write-Host "AVISO: $msg" -ForegroundColor Yellow
}

function Get-PhpModules() {
    if (-not (Test-Command php)) { return @() }

    $modules = @()
    $capture = $false
    foreach ($line in (& php -m 2>$null)) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "[PHP Modules]") {
            $capture = $true
            continue
        }
        if ($trimmed -eq "[Zend Modules]") { break }
        if ($capture -and $trimmed) {
            $modules += $trimmed.ToLowerInvariant()
        }
    }

    return $modules
}

function Ensure-PhpExtensions([array]$requiredExtensions) {
    if (-not (Test-Command php)) { return }

    try {
        $iniOut = php --ini 2>$null
        $loadedLine = $iniOut | Select-String -Pattern "Loaded Configuration File" | Select-Object -First 1
        $iniPath = $null

        if ($loadedLine) {
            $iniPath = ($loadedLine -split ":\s*", 2)[1].Trim()
        }

        if (-not $iniPath -or $iniPath -eq "(none)") {
            $iniPath = "C:\php\php.ini"
        }

        if (-not (Test-Path $iniPath)) {
            Write-Warn "No se encontro php.ini para validar extensiones (buscado: $iniPath)."
            return
        }

        $content = Get-Content $iniPath -Raw
        $extDirLine = (& php -i 2>$null | Select-String -Pattern "^extension_dir =>" | Select-Object -First 1)
        $extDir = $null
        if ($extDirLine) {
            $parts = ($extDirLine.ToString() -split "=>") | ForEach-Object { $_.Trim() }
            if ($parts.Count -ge 2) { $extDir = $parts[1] }
        }

        if (-not $extDir -or $extDir -eq "no value") {
            $extDir = Join-Path (Split-Path -Parent $iniPath) "ext"
        }

        # Keep php.ini tidy: duplicate extension lines produce noisy warnings on every artisan command.
        foreach ($ext in $requiredExtensions) {
            $escaped = [regex]::Escape($ext)
            $activePattern = "^\s*extension=(php_)?$escaped(\.dll)?\s*$"
            $lines = $content -split "\r?\n"
            $seenActive = $false
            for ($i = 0; $i -lt $lines.Count; $i++) {
                if ($lines[$i] -match $activePattern) {
                    if ($seenActive) {
                        $lines[$i] = "; " + $lines[$i].Trim() + " ; duplicada por instalador RestauranteERP"
                    } else {
                        $seenActive = $true
                    }
                }
            }

            if ($seenActive) {
                $content = $lines -join [Environment]::NewLine
            }
        }

        $loadedExtensions = Get-PhpModules
        $missingExtensions = @($requiredExtensions | Where-Object { $loadedExtensions -notcontains $_.ToLowerInvariant() })
        if ($missingExtensions.Count -eq 0) {
            Set-Content -Path $iniPath -Value $content -Encoding UTF8
            Write-Host "  Extensiones PHP requeridas ya estan cargadas." -ForegroundColor Green
            return
        }

        $updated = $false
        foreach ($ext in $missingExtensions) {
            $escaped = [regex]::Escape($ext)
            $dllPath = Join-Path $extDir "php_$ext.dll"
            if (-not (Test-Path $dllPath)) {
                Write-Warn "La extension PHP '$ext' no esta cargada y no existe $dllPath. Instala una distribucion PHP que incluya esa extension."
                continue
            }

            if ($content -match "(?m)^\s*;\s*extension=(php_)?$escaped(\.dll)?\s*$") {
                $content = [regex]::Replace($content, "(?m)^\s*;\s*extension=(php_)?$escaped(\.dll)?\s*$", "extension=$ext", 1)
                $updated = $true
            } elseif ($content -notmatch "(?m)^\s*extension=(php_)?$escaped(\.dll)?\s*$") {
                $content += [Environment]::NewLine + "extension=$ext"
                $updated = $true
            }
        }

        if ($updated) {
            Set-Content -Path $iniPath -Value $content -Encoding UTF8
            Write-Host "  Extensiones PHP requeridas ajustadas en php.ini." -ForegroundColor Green
        } else {
            Write-Host "  Extensiones PHP requeridas ya estaban habilitadas." -ForegroundColor Green
        }
    } catch {
        Write-Warn "No se pudo validar/extender php.ini: $($_.Exception.Message)"
    }
}

function Grant-AppFolderPermissions([string]$path) {
    if (-not (Test-Path $path)) { return }

    try {
        $resolved = (Resolve-Path $path).Path
        $grant = "*S-1-5-32-545:(OI)(CI)M"
        $quotedPath = '"' + $resolved.Replace('"', '""') + '"'
        $quotedGrant = '"' + $grant + '"'
        cmd /c "icacls $quotedPath /grant $quotedGrant /T /C >NUL 2>NUL"

        if ($LASTEXITCODE -ne 0) {
            Write-Warn "No se pudieron ajustar permisos en $resolved. Ejecuta el instalador como Administrador si la app no puede escribir ahi."
        }
    } catch {
        Write-Warn "No se pudieron ajustar permisos en ${path}: $($_.Exception.Message)"
    }
}

function Ensure-LaravelWritableDirectories([string]$basePath) {
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
        New-Item -ItemType Directory -Force -Path (Join-Path $basePath $dir) | Out-Null
    }

    Grant-AppFolderPermissions $basePath
}

function New-AppShortcut(
    [object]$WshShell,
    [string]$ShortcutPath,
    [string]$TargetPath,
    [string]$WorkingDirectory,
    [string]$Description,
    [string]$IconPath = ""
) {
    $shortcutDir = Split-Path -Parent $ShortcutPath
    if ($shortcutDir -and -not (Test-Path $shortcutDir)) {
        New-Item -ItemType Directory -Force -Path $shortcutDir | Out-Null
    }

    $shortcut = $WshShell.CreateShortcut($ShortcutPath)
    $shortcut.TargetPath = $TargetPath
    $shortcut.WorkingDirectory = $WorkingDirectory
    $shortcut.Description = $Description
    if ($IconPath -and (Test-Path $IconPath)) {
        $shortcut.IconLocation = $IconPath
    } else {
        $shortcut.IconLocation = "$env:SystemRoot\System32\shell32.dll,220"
    }
    $shortcut.Save()
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
Write-Host "Autoria: creado por el Ing. Josue Villacorte. Empresa en desarrollo: Zenith."
Write-Host ""

# Preflight de prerequisitos (banderas)
$pre_php = Test-Command php
$pre_composer = Test-Command composer
$pre_node = Test-Command node
$pre_npm = Test-Command npm
$pre_pnpm = Test-Command pnpm
$pre_psql = Test-Command psql
$pre_postgres_service = $null -ne (Get-Service -Name "postgresql*" -ErrorAction SilentlyContinue)

# --- Requisitos ---
Write-Step "Comprobando requisitos..."

if (-not $pre_php) {
    Write-Host "ERROR: PHP no esta en el PATH." -ForegroundColor Red
    Write-Host "Instala PHP 8.2+ (Laragon, XAMPP o php.net) y vuelve a ejecutar."
    if (-not $Silent) { Read-Host "Enter para salir" }
    exit 1
}

$phpVerOutput = (& php -r "echo PHP_VERSION;" 2>$null) -join " "
$phpVerMatch = [regex]::Match($phpVerOutput, "\d+\.\d+\.\d+")
$phpVer = if ($phpVerMatch.Success) { $phpVerMatch.Value } else { $phpVerOutput.Trim() }
Write-Host "  PHP: $phpVer" -ForegroundColor Green

$requiredPhpExt = @(
    "pdo_pgsql","pgsql","pdo_sqlite","sqlite3","mbstring","openssl","curl","fileinfo",
    "gd","intl","zip","bcmath","sodium","exif"
)
Ensure-PhpExtensions $requiredPhpExt

if (-not $pre_composer) {
    Write-Host "AVISO: Composer no encontrado. Se usara vendor incluido si existe." -ForegroundColor Yellow
} else {
    Write-Host "  Composer: OK" -ForegroundColor Green
}

if ($pre_postgres_service -or $pre_psql) {
    Write-Host "  PostgreSQL: detectado" -ForegroundColor Green
} else {
    Write-Warn "PostgreSQL no detectado (se usara SQLite local por defecto)."
}

if ($pre_node) {
    Write-Host "  Node.js: OK" -ForegroundColor Green
}
if ($pre_pnpm) {
    Write-Host "  pnpm: OK" -ForegroundColor Green
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
Ensure-LaravelWritableDirectories $InstallDir

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
} elseif ($pre_composer) {
    Write-Step "Instalando dependencias PHP (composer)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudieron instalar las dependencias PHP. La instalacion se detuvo."
    }
} else {
    Write-Host "ERROR: No existe vendor\autoload.php y Composer no esta instalado." -ForegroundColor Red
    Write-Host "Solucion: genera el instalador desde una carpeta con 'composer install' ya ejecutado, o instala Composer en este equipo."
    if (-not $Silent) { Read-Host "Enter para salir" }
    exit 1
}

php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw "No se pudo limpiar la configuracion anterior." }
php artisan app:ensure-key
if ($LASTEXITCODE -ne 0) { throw "No se pudo preparar la clave de la aplicacion." }

# --- Frontend / pnpm ---
if (Test-Path "package.json") {
    if (Test-Path "public\build\manifest.json") {
        Write-Step "Assets frontend ya compilados (public/build)."
    } else {
        Write-Step "Validando dependencias frontend..."
        if ($pre_pnpm) {
            pnpm install --frozen-lockfile
            if ($LASTEXITCODE -ne 0) { throw "Fallo la instalacion de dependencias frontend." }
            pnpm run build
            if ($LASTEXITCODE -ne 0) { throw "Fallo la compilacion frontend." }
            Write-Host "  Build frontend completado con pnpm." -ForegroundColor Green
        } elseif ($pre_npm) {
            Write-Warn "pnpm no disponible. Instalalo manualmente y luego ejecuta:"
            Write-Warn "npm install -g pnpm"
            Write-Warn "pnpm install"
            Write-Warn "pnpm run build"
        } else {
            Write-Warn "Node.js/npm no disponible. Frontend no compilado."
            Write-Warn "Instala Node.js y luego ejecuta:"
            Write-Warn "npm install -g pnpm"
            Write-Warn "pnpm install"
            Write-Warn "pnpm run build"
        }
    }
}

if (-not (Test-Path "public\build\manifest.json")) {
    throw "Faltan los assets compilados. Incluye public/build en el instalador o compila con pnpm."
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
Write-Step "Permisos de carpetas Laravel..."
Ensure-LaravelWritableDirectories $InstallDir

# --- Migraciones ---
Write-Step "Migrando base de datos..."
php artisan migrate --force 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "La migracion fallo. La instalacion se detuvo; revisa el motor configurado y el registro antes de reintentar."
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

# Ajustar launcher en destino
$destLauncher = Join-Path $InstallDir "desktop\Iniciar-RestauranteERP.bat"
$destInstaller = Join-Path $InstallDir "desktop\Instalar-RestauranteERP.bat"
$appIcon = Join-Path $InstallDir "desktop\RestauranteERP.exe"
$destDesktop = [Environment]::GetFolderPath("Desktop")
$programs = [Environment]::GetFolderPath("Programs")
$startMenuDir = Join-Path $programs "Restaurante ERP"

New-AppShortcut `
    -WshShell $WshShell `
    -ShortcutPath (Join-Path $destDesktop "Restaurante ERP.lnk") `
    -TargetPath $destLauncher `
    -WorkingDirectory $InstallDir `
    -Description "Iniciar Restaurante ERP" `
    -IconPath $appIcon

New-AppShortcut `
    -WshShell $WshShell `
    -ShortcutPath (Join-Path $startMenuDir "Restaurante ERP.lnk") `
    -TargetPath $destLauncher `
    -WorkingDirectory $InstallDir `
    -Description "Iniciar Restaurante ERP" `
    -IconPath $appIcon

New-AppShortcut `
    -WshShell $WshShell `
    -ShortcutPath (Join-Path $startMenuDir "Configurar o actualizar Restaurante ERP.lnk") `
    -TargetPath $destInstaller `
    -WorkingDirectory $InstallDir `
    -Description "Configurar o actualizar Restaurante ERP" `
    -IconPath $appIcon

Write-Host "  Acceso directo creado en Escritorio: Restaurante ERP" -ForegroundColor Green
Write-Host "  Accesos creados en Menu Inicio: Restaurante ERP" -ForegroundColor Green

# --- Archivo de ruta instalada ---
$infoFile = Join-Path $InstallDir "INSTALADO.txt"
@"
ERP Restaurante instalado: $(Get-Date -Format "yyyy-MM-dd HH:mm")
Ruta: $InstallDir
URL local: http://127.0.0.1:$Port/login
Tablets (misma WiFi): http://TU-IP-LAN:$Port
Autoria: creado por el Ing. Josue Villacorte. Empresa en desarrollo: Zenith.
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
