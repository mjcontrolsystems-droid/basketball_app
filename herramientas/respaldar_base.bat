@echo off
rem Doble clic aqui para respaldar la base de datos de la liga.
rem El trabajo real lo hace respaldar_base.ps1 (misma carpeta).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0respaldar_base.ps1"
