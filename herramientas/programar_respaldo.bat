@echo off
rem Doble clic aqui para que el respaldo corra solo todos los dias a las 20:00.
rem Para quitarlo, arrastra la palabra "quitar" o corre:  programar_respaldo.bat quitar
if /I "%~1"=="quitar" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0programar_respaldo.ps1" -Quitar
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0programar_respaldo.ps1"
)
