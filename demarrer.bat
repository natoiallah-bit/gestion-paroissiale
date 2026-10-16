@echo off
cd /d "%~dp0"
title Gestion Paroissiale - serveur (laisser ouvert)
call "%~dp0outils\trouver-php.bat"
if not defined PHP (echo PHP est introuvable sur cet ordinateur. & pause & exit /b 1)
if not exist vendor\autoload.php (echo Lancez d'abord installer.bat & pause & exit /b 1)
"%PHP%" artisan migrate --force
"%PHP%" artisan paroisse:adresses --port=8200
echo  Laissez cette fenetre ouverte pendant que la paroisse travaille.
echo  Si Windows demande l'autorisation pour PHP : cochez "Reseaux prives" puis Autoriser.
echo.
start "" http://127.0.0.1:8200
"%PHP%" artisan serve --host=0.0.0.0 --port=8200
echo.
echo Le serveur s'est arrete (voir le message ci-dessus).
pause
