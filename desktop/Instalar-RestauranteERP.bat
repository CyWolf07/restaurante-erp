@echo off
title Instalador Completo ERP Restaurante
cd /d "%~dp0"

:: ============================================
::  Verificar permisos de administrador
:: ============================================
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Solicitando permisos de administrador...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

echo ============================================
echo   INSTALADOR COMPLETO ERP RESTAURANTE
echo   Instala: PHP, PostgreSQL, Composer,
echo            Node.js y la aplicacion
echo ============================================
echo.
echo Este proceso instalara todo lo necesario.
echo Requiere conexion a internet.
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Instalar-RestauranteERP.ps1"
if errorlevel 1 (
    echo.
    echo La instalacion termino con errores.
    echo Revisa los mensajes arriba.
    pause
    exit /b 1
)

pause
