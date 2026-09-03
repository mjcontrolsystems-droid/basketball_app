@echo off
rem Doble clic aqui para comprobar que el ultimo respaldo SIRVE de verdad.
rem No se conecta a la base de la liga: todo el trabajo es sobre el archivo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0verificar_respaldo.ps1"
