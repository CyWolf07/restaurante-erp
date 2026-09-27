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
    echo Instala PHP 8.2+ o ejecuta el instalador completo.
    echo Ejecuta: desktop\Instalar-RestauranteERP.bat
    pause
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo ERROR: No existe vendor\autoload.php.
    echo Ejecuta desktop\Instalar-RestauranteERP.bat primero.
    pause
    exit /b 1
)

if not exist "database" mkdir "database" >nul 2>&1
if not exist "storage\logs" mkdir "storage\logs" >nul 2>&1
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" >nul 2>&1
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions" >nul 2>&1
if not exist "storage\framework\views" mkdir "storage\framework\views" >nul 2>&1
if not exist "storage\framework\compiled_views" mkdir "storage\framework\compiled_views" >nul 2>&1
if not exist "bootstrap\cache" mkdir "bootstrap\cache" >nul 2>&1

if not exist "storage\logs" (
    echo ERROR: No se pudo crear storage\logs.
    echo La app no tiene permisos de escritura en esta carpeta.
    echo Ejecuta desktop\Instalar-RestauranteERP.bat como Administrador o instala en C:\RestauranteERP.
    pause
    exit /b 1
)

if not exist "bootstrap\cache" (
    echo ERROR: No se pudo crear bootstrap\cache.
    echo La app no tiene permisos de escritura en esta carpeta.
    echo Ejecuta desktop\Instalar-RestauranteERP.bat como Administrador o instala en C:\RestauranteERP.
    pause
    exit /b 1
)

if not exist ".env" (
    echo Copiando .env.example ...
    copy ".env.example" ".env" >nul
    if errorlevel 1 (
        echo ERROR: No se pudo crear .env.
        echo La app no tiene permisos de escritura en esta carpeta.
        echo Ejecuta desktop\Instalar-RestauranteERP.bat como Administrador o instala en C:\RestauranteERP.
        pause
        exit /b 1
    )
)

php artisan config:clear
if errorlevel 1 exit /b 1
php artisan app:ensure-key
if errorlevel 1 exit /b 1

set SERVER_HOST=0.0.0.0
set SERVER_PORT=8080

for /f "tokens=1,* delims==" %%A in ('findstr /b /c:"SERVER_HOST=" ".env" 2^>nul') do set SERVER_HOST=%%B
for /f "tokens=1,* delims==" %%A in ('findstr /b /c:"SERVER_PORT=" ".env" 2^>nul') do set SERVER_PORT=%%B
set SERVER_HOST=%SERVER_HOST:"=%
set SERVER_PORT=%SERVER_PORT:"=%

:: Verificar que los assets esten compilados
if not exist "public\build\manifest.json" (
    echo Assets no compilados. Compilando con pnpm...
    where pnpm >nul 2>&1
    if errorlevel 1 (
        echo Instalando pnpm...
        call npm install -g pnpm@9.15.4 >nul 2>&1
    )
    where pnpm >nul 2>&1
    if not errorlevel 1 (
        call pnpm install --frozen-lockfile
        if errorlevel 1 exit /b 1
        call pnpm run build
        if errorlevel 1 exit /b 1
    ) else (
        echo AVISO: pnpm no disponible. Ejecuta manualmente:
        echo   npm install -g pnpm
        echo   pnpm install
        echo   pnpm run build
    )
)

if not exist "public\build\manifest.json" (
    echo ERROR: Faltan assets compilados. Ejecuta el instalador o pnpm run build.
    pause
    exit /b 1
)

echo Migrando base de datos...
php artisan migrate --force
if errorlevel 1 (
    echo.
    echo ERROR: Las migraciones fallaron. Revisa .env y la conexion de base de datos.
    pause
    exit /b 1
)

echo.
echo ============================================
echo   SERVIDOR INICIADO
echo ============================================
echo.
echo   Local:   http://127.0.0.1:%SERVER_PORT%/login
echo   Tablets: http://TU-IP:%SERVER_PORT% (misma WiFi)
echo.
echo   Presiona Ctrl+C para detener.
echo.

start "" "http://127.0.0.1:%SERVER_PORT%/login"
php artisan serve --host=%SERVER_HOST% --port=%SERVER_PORT%

pause
