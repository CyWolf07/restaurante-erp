# Genera RestauranteERP.exe desde el lanzador .bat
# Requiere: Install-Module ps2exe -Scope CurrentUser

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$bat  = Join-Path $PSScriptRoot "Iniciar-RestauranteERP.bat"
$exe  = Join-Path $PSScriptRoot "RestauranteERP.exe"

if (-not (Get-Module -ListAvailable -Name ps2exe)) {
    Write-Host "Instalando modulo ps2exe..."
    Install-Module ps2exe -Scope CurrentUser -Force
}

Import-Module ps2exe

Write-Host "Compilando $exe ..."
Invoke-ps2exe -inputFile $bat -outputFile $exe -title "Restaurante ERP" -iconFile $null -noConsole:$false

Write-Host ""
Write-Host "Listo. Ejecuta: desktop\RestauranteERP.exe"
Write-Host ""
Write-Host "Para generar TAMBIEN el instalador, usa: .\crear-instalador.ps1"
Write-Host "Coloca los .exe en la carpeta desktop del proyecto (restaurant-erp)."
