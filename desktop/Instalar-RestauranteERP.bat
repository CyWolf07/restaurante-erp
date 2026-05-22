@echo off
title Instalador ERP Restaurante
cd /d "%~dp0"

echo Ejecutando instalador...
echo Si Windows bloquea el script, clic derecho - Ejecutar como administrador.
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Instalar-RestauranteERP.ps1"
if errorlevel 1 (
    echo.
    echo La instalacion termino con errores.
    pause
    exit /b 1
)

pause
