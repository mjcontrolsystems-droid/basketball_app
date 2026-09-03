@echo off
rem ---------------------------------------------------------------------------
rem  Doble clic aqui para comprobar la logica de la liga.
rem
rem  Corre las pruebas de tabla de posiciones, suspensiones, multas y calendario.
rem  No toca la base de datos ni el sitio publicado: solo revisa que las reglas
rem  sigan dando el resultado correcto. Se puede correr en cualquier momento.
rem ---------------------------------------------------------------------------
chcp 65001 >nul
setlocal

rem Busca php.exe: primero en el PATH, despues en las instalaciones tipicas.
set "PHP="
where php.exe >nul 2>nul && set "PHP=php.exe"

if not defined PHP (
    for %%R in ("C:\xampp\php\php.exe" "C:\laragon\bin\php\php.exe" "C:\php\php.exe" "C:\wamp64\bin\php\php.exe") do (
        if exist %%R if not defined PHP set "PHP=%%~R"
    )
)

rem Laragon y WAMP guardan cada version en su propia subcarpeta.
if not defined PHP (
    for /d %%D in ("C:\laragon\bin\php\php-*") do (
        if exist "%%D\php.exe" if not defined PHP set "PHP=%%D\php.exe"
    )
)
if not defined PHP (
    for /d %%D in ("C:\wamp64\bin\php\php*") do (
        if exist "%%D\php.exe" if not defined PHP set "PHP=%%D\php.exe"
    )
)

if not defined PHP (
    echo.
    echo No se encontro PHP en esta computadora.
    echo.
    echo Las pruebas necesitan PHP para correr. Se descarga de:
    echo   https://windows.php.net/download/
    echo.
    echo Busca "PHP 8.3" y baja el Zip de "VS16 x64 Non Thread Safe".
    echo Se descomprime en C:\php y listo: no hay que instalar nada mas.
    echo.
    pause
    exit /b 1
)

echo Usando: %PHP%
echo.

rem El resultado se guarda ademas en un archivo: la ventana se cierra al presionar una
rem tecla y con ella se perdia todo lo que habia que leer o pasar por chat.
set "SALIDA=%~dp0ultimo_resultado.txt"
"%PHP%" "%~dp0correr.php" > "%SALIDA%" 2>&1
set CODIGO=%ERRORLEVEL%
type "%SALIDA%"

echo.
if %CODIGO%==0 (
    echo Todo en orden. Ya se puede subir el cambio.
) else (
    echo Hay pruebas fallando. NO subas el cambio hasta arreglarlas.
)
echo.
echo El resultado quedo guardado en:
echo   %SALIDA%
echo.
pause
exit /b %CODIGO%
