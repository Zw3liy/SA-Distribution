@echo off
REM ============================================================
REM  SA Business Distribution — local Windows dev setup
REM
REM  Downloads and installs a real PHP runtime, adds it to PATH,
REM  and starts the built-in PHP dev server from the project root.
REM  Replaces the old approach of committing a portable PHP
REM  runtime into the repository.
REM ============================================================
setlocal

REM === CONFIG ===
set PHP_URL=https://windows.php.net/downloads/releases/php-8.3.15-Win32-vs16-x64.zip
set PHP_DIR=C:\php
set PROJECT_DIR=%~dp0..

REM === DOWNLOAD PHP ===
echo Downloading PHP...
powershell -Command "Invoke-WebRequest %PHP_URL% -OutFile %USERPROFILE%\Downloads\php.zip"

REM === EXTRACT PHP ===
echo Extracting PHP...
powershell -Command "Expand-Archive %USERPROFILE%\Downloads\php.zip -DestinationPath %PHP_DIR% -Force"

REM === CONFIGURE PHP ===
echo Configuring php.ini...
copy %PHP_DIR%\php.ini-development %PHP_DIR%\php.ini

REM === ADD TO PATH ===
echo Adding PHP to PATH for this user...
setx PATH "%PATH%;%PHP_DIR%"

REM === VERIFY INSTALLATION ===
echo Verifying PHP installation...
%PHP_DIR%\php.exe -v

REM === START SERVER ===
echo.
echo Starting PHP built-in server at http://localhost:8000
echo (Requires a MySQL/MariaDB server with the sa_business database already created — see README.md)
echo.
cd /d "%PROJECT_DIR%"
%PHP_DIR%\php.exe -S localhost:8000

endlocal
pause
