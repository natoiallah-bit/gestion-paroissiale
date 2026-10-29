@echo off
rem Cherche php.exe : d'abord dans le PATH, puis dans Laragon, XAMPP et WAMP
set "PHP="
rem PHP livre avec le paquet serveur (dossier php a cote de ce projet)
if exist "%~dp0..\php\php.exe" set "PHP=%~dp0..\php\php.exe"
if defined PHP goto :fin
for /f "delims=" %%p in ('where php 2^>nul') do if not defined PHP set "PHP=%%p"
if not defined PHP for /d %%d in ("C:\laragon\bin\php\php-8*") do set "PHP=%%d\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /d %%d in ("C:\wamp64\bin\php\php8*") do set "PHP=%%d\php.exe"
if not defined PHP for /d %%d in ("C:\php*") do if exist "%%d\php.exe" set "PHP=%%d\php.exe"
:fin
