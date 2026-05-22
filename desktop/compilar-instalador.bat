@echo off
title Compilar RestauranteERP-Setup.exe (Inno Setup 6)
cd /d "%~dp0"

set ISCC=
if exist "%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe" set ISCC=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe
if exist "%ProgramFiles%\Inno Setup 6\ISCC.exe" set ISCC=%ProgramFiles%\Inno Setup 6\ISCC.exe

if "%ISCC%"=="" (
    echo ERROR: No se encontro Inno Setup 6.
    echo Instalalo desde https://jrsoftware.org/isinfo.php
    pause
    exit /b 1
)

echo ============================================
echo   COMPILAR INSTALADOR - Inno Setup 6
echo ============================================
echo.
echo Compilador: %ISCC%
echo Script:     %~dp0setup.iss
echo Salida:     %~dp0Output\RestauranteERP-Setup.exe
echo.

if not exist "RestauranteERP.exe" (
    echo AVISO: No existe RestauranteERP.exe en desktop\
    echo El instalador usara el .bat para iniciar el servidor.
    echo Opcional: ejecuta crear-instalador.ps1 antes para generar los .exe
    echo.
)

"%ISCC%" "%~dp0setup.iss"
if errorlevel 1 (
    echo.
    echo ERROR en la compilacion. Revisa los mensajes arriba.
    pause
    exit /b 1
)

echo.
echo ============================================
echo   LISTO
echo ============================================
echo Instalador: %~dp0Output\RestauranteERP-Setup.exe
echo.
if exist "%~dp0Output\RestauranteERP-Setup.exe" (
    explorer /select,"%~dp0Output\RestauranteERP-Setup.exe"
)
pause
