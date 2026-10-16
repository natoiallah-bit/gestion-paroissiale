@echo off
cd /d "%~dp0"
title Installation - Gestion Paroissiale
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. Installez PHP 8.3 ou plus, ou Laragon. & pause & exit /b 1)
"%PHP%" outils\installer.php
echo.
pause
