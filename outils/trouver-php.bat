@echo off
rem Cherche php.exe : d'abord dans le PATH, puis dans Laragon, XAMPP et WAMP
set "PHP="
for /f "delims=" %%p in ('where php 2^>nul') do if not defined PHP set "PHP=%%p"
if not defined PHP for /d %%d in ("C:\laragon\bin\php\php-8*") do set "PHP=%%d\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /d %%d in ("C:\wamp64\bin\php\php8*") do set "PHP=%%d\php.exe"
if not defined PHP for /d %%d in ("C:\php*") do if exist "%%d\php.exe" set "PHP=%%d\php.exe"
