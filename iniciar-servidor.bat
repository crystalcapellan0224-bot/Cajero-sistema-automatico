@echo off
setlocal enabledelayedexpansion
title CajaRapida - Servidor local

set "PUERTO=8000"
set "PHP="
set "MYSQLD="
set "MYSQLINI="

for /d %%D in ("C:\laragon\bin\php\*") do (
    if exist "%%~fD\php.exe" set "PHP=%%~fD\php.exe"
)

for /d %%D in ("C:\laragon\bin\mysql\*") do (
    if exist "%%~fD\bin\mysqld.exe" (
        set "MYSQLD=%%~fD\bin\mysqld.exe"
        set "MYSQLINI=%%~fD\my.ini"
    )
)

if "%PHP%"=="" (
    where php.exe >nul 2>nul
    if not errorlevel 1 set "PHP=php.exe"
)

if "%PHP%"=="" (
    echo.
    echo   No se encontro PHP. Instala PHP o copia esta carpeta dentro de tu www de Laragon.
    echo.
    pause
    exit /b 1
)

echo.
echo   ============================================
echo    CajaRapida - Sistema de cajero automatico
echo   ============================================
echo.

netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if errorlevel 1 (
    if not "%MYSQLD%"=="" (
        echo   Iniciando MySQL...
        start "" "%MYSQLD%" --defaults-file="%MYSQLINI%"
        timeout /t 5 /nobreak >nul
    ) else (
        echo   MySQL no esta corriendo y no se encontro mysqld.
        echo   Abre MySQL manualmente y vuelve a ejecutar este archivo.
        pause
        exit /b 1
    )
) else (
    echo   MySQL ya estaba corriendo.
)

echo   Iniciando servidor web en el puerto %PUERTO%...
echo   URL: http://localhost:%PUERTO%/
echo   Presiona Ctrl+C para detener el servidor.
echo.

start "" "http://localhost:%PUERTO%/"
"%PHP%" -S localhost:%PUERTO% -t "%~dp0"

endlocal
