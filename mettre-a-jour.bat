@echo off
cd /d "%~dp0"
title Mise a jour - Gestion Paroissiale
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. & pause & exit /b 1)
if exist .git (git pull) else (echo Projet telecharge en ZIP : retelechargez le nouveau ZIP et copiez-y votre fichier database\database.sqlite)
"%PHP%" outils\installer.php
echo.
pause
