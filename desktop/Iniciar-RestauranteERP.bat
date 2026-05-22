@echo off
title Restaurante ERP - Servidor Local
cd /d "%~dp0.."

echo ============================================
echo   ERP RESTAURANTE - Servidor LAN Local
echo ============================================
echo.

where php >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP no esta en el PATH.
    echo Instala PHP 8.2+ o usa Laragon/XAMPP.
    pause
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo ERROR: No existe vendor\autoload.php.
    echo Ejecuta desktop\Instalar-RestauranteERP.bat o instala dependencias con composer install.
    pause
    exit /b 1
)

if not exist ".env" (
    echo Copiando .env.example ...
    copy ".env.example" ".env" >nul
    php artisan key:generate --force
)

set SERVER_HOST=0.0.0.0
set SERVER_PORT=8080

for /f "tokens=1,* delims==" %%A in ('findstr /b /c:"SERVER_HOST=" ".env" 2^>nul') do set SERVER_HOST=%%B
for /f "tokens=1,* delims==" %%A in ('findstr /b /c:"SERVER_PORT=" ".env" 2^>nul') do set SERVER_PORT=%%B
set SERVER_HOST=%SERVER_HOST:"=%
set SERVER_PORT=%SERVER_PORT:"=%

if not exist "database" mkdir "database"
if not exist "database\database.sqlite" type nul > "database\database.sqlite"

echo Migrando base de datos...
php artisan migrate --force
if errorlevel 1 (
    echo.
    echo ERROR: Las migraciones fallaron. Revisa .env y la conexion de base de datos.
    pause
    exit /b 1
)

echo.
echo Iniciando servidor en http://%SERVER_HOST%:%SERVER_PORT%
echo Tablets: abre http://TU-IP:%SERVER_PORT% en la misma red WiFi
echo.
echo Presiona Ctrl+C para detener.
echo.

start "" "http://127.0.0.1:%SERVER_PORT%/login"
php artisan serve --host=%SERVER_HOST% --port=%SERVER_PORT%

pause
