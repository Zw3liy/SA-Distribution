@echo off
setlocal

REM === CONFIG ===
set PHP_URL=https://windows.php.net/downloads/releases/php-8.5.8-Win32-vs17-x64.zip
set PHP_DIR=C:\php
set PROJECT_DIR=C:\Users\HP\OneDrive\Desktop\Zwelithini\SA Distribution

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
echo Adding PHP to PATH...
setx PATH "%PATH%;%PHP_DIR%"

REM === VERIFY INSTALLATION ===
echo Verifying PHP installation...
php -v

REM === START SERVER ===
echo Starting PHP server...
cd %PROJECT_DIR%
php -S localhost:8000

endlocal
pause
