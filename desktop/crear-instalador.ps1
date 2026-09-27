# Genera ejecutables .exe para instalar y arrancar el ERP
# Requiere: modulo ps2exe (se instala automaticamente si falta)
# Uso (PowerShell como usuario normal):
#   cd desktop
#   .\crear-instalador.ps1

$ErrorActionPreference = "Stop"
$DesktopDir = $PSScriptRoot

function Ensure-Ps2Exe {
    if (-not (Get-Module -ListAvailable -Name ps2exe)) {
        Write-Host "Instalando modulo ps2exe desde PSGallery..."
        Set-PSRepository -Name PSGallery -InstallationPolicy Trusted -ErrorAction SilentlyContinue
        Install-Module ps2exe -Scope CurrentUser -Force
    }
    Import-Module ps2exe -Force
}

function Build-Exe([string]$inputBat, [string]$outputExe, [string]$title, [bool]$noConsole = $false) {
    if (-not (Test-Path $inputBat)) {
        throw "No existe: $inputBat"
    }
    Write-Host "Compilando $outputExe ..."
    $params = @{
        inputFile  = $inputBat
        outputFile = $outputExe
        title      = $title
        noConsole  = $noConsole
    }
    Invoke-ps2exe @params
    if (Test-Path $outputExe) {
        Write-Host "  OK: $outputExe" -ForegroundColor Green
    }
}

Ensure-Ps2Exe

Write-Host ""
Write-Host "=== Generando ejecutables ERP Restaurante ===" -ForegroundColor Yellow
Write-Host ""

# 1) Instalador (.exe que lanza PowerShell con el script de instalacion)
$installWrapper = Join-Path $DesktopDir "_wrap-instalar.bat"
@"
@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Instalar-RestauranteERP.ps1"
pause
"@ | Set-Content -Path $installWrapper -Encoding ASCII

Build-Exe `
    -inputBat $installWrapper `
    -outputExe (Join-Path $DesktopDir "Instalar-RestauranteERP.exe") `
    -title "Instalar Restaurante ERP" `
    -noConsole $false

Remove-Item $installWrapper -Force -ErrorAction SilentlyContinue

# 2) Lanzador del servidor
Build-Exe `
    -inputBat (Join-Path $DesktopDir "Iniciar-RestauranteERP.bat") `
    -outputExe (Join-Path $DesktopDir "RestauranteERP.exe") `
    -title "Restaurante ERP" `
    -noConsole $false

Write-Host ""
Write-Host "Listo. Archivos en carpeta desktop:" -ForegroundColor Green
Write-Host "  Instalar-RestauranteERP.exe  -> primera instalacion en el PC"
Write-Host "  RestauranteERP.exe           -> iniciar servidor cada dia"
Write-Host ""
Write-Host "NOTA: El instalador usa pnpm (seguro) en lugar de npm." -ForegroundColor Cyan
Write-Host "      Incluye verificacion de antiguedad de paquetes (30 dias min)." -ForegroundColor Cyan
Write-Host ""
Write-Host "Para generar un instalador MSI profesional:" -ForegroundColor Cyan
Write-Host "  1. Instala Inno Setup: https://jrsoftware.org/isinfo.php"
Write-Host "  2. Abre desktop\setup.iss y compila (Build -> Compile)"
Write-Host "  3. Obtendras desktop\Output\RestauranteERP-Setup.exe"
Write-Host ""
