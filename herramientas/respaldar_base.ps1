# Respaldo de la base de datos de la plataforma (Neon PostgreSQL).
#
# Qué hace, en orden:
#   1. Lee DATABASE_URL del archivo .env del proyecto (no hay credenciales escritas aquí).
#   2. Le quita el "-pooler" al host: pg_dump necesita la conexión directa.
#   3. Genera un respaldo comprimido y fechado en la carpeta "Respaldos BD".
#   4. COMPRUEBA el archivo recién creado: que se pueda leer y que traiga las tablas
#      importantes. Un respaldo roto en silencio es peor que no tener respaldo, porque
#      da una tranquilidad falsa hasta el día que hace falta.
#   5. Anota el resultado en "Respaldos BD\registro.txt".
#   6. Borra los respaldos con más de 90 días.
#
# Se ejecuta con doble clic en respaldar_base.bat, o a mano:
#   powershell -ExecutionPolicy Bypass -File herramientas\respaldar_base.ps1
#
# Con -Silencioso no espera que nadie presione una tecla: es como lo corre la tarea
# programada (ver programar_respaldo.ps1).

param([switch]$Silencioso)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot '_comun.ps1')

$raiz = Split-Path -Parent $PSScriptRoot
$carpetaRespaldos = Join-Path $raiz 'Respaldos BD'
$fecha = Get-Date -Format 'yyyy-MM-dd_HHmm'
$destino = Join-Path $carpetaRespaldos "liga_$fecha.dump"

function Salir {
    param([int]$Codigo)
    if (-not $Silencioso) {
        Read-Host 'Presiona Enter para cerrar'
    }
    exit $Codigo
}

Write-Host ''
Write-Host '=== Respaldo de la base de datos ===' -ForegroundColor Cyan

# --- 1. Herramientas ---
$pgDump = Get-HerramientaPg -Nombre 'pg_dump'
$pgRestore = Get-HerramientaPg -Nombre 'pg_restore'
if (-not $pgDump -or -not $pgRestore) {
    Write-InstruccionesPostgres
    Write-Registro -Carpeta $carpetaRespaldos -Mensaje 'FALLO: no se encontro pg_dump/pg_restore'
    Salir 1
}
Write-Host "Usando: $pgDump"

# --- 2. Conexión ---
try {
    $urlDirecta = Get-UrlBase -Raiz $raiz
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Registro -Carpeta $carpetaRespaldos -Mensaje "FALLO: $($_.Exception.Message)"
    Salir 1
}

# --- 3. Generar el respaldo ---
New-Item -ItemType Directory -Force -Path $carpetaRespaldos | Out-Null
Write-Host "Respaldando hacia: $destino"
Write-Host 'Esto tarda unos segundos...'

$salida = & $pgDump --format=custom --no-owner --no-privileges --file=$destino $urlDirecta 2>&1
$salidaTexto = ($salida | Out-String)
if ($salidaTexto.Trim() -ne '') { Write-Host $salidaTexto }

if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host 'El respaldo FALLO.' -ForegroundColor Red

    # El error más común y el más confuso: pg_dump se niega a respaldar un servidor más
    # nuevo que él. Neon actualiza su Postgres solo, así que esto reaparece cada tanto sin
    # que nadie haya tocado nada. En vez de repetir la teoría, se lee la version exacta del
    # mensaje y se dice cuál instalar.
    if ($salidaTexto -match 'server version:\s*(\d+)') {
        $versionServidor = $Matches[1]
        Write-Host ''
        Write-Host "La base de la liga ya va en PostgreSQL $versionServidor y esta computadora tiene una version" -ForegroundColor Yellow
        Write-Host 'mas vieja. pg_dump se niega a respaldar un servidor mas nuevo que el.'
        Write-Host ''
        Write-Host "Solucion (una sola vez): instalar las herramientas de la version $versionServidor." -ForegroundColor Yellow
        Write-Host '  1. Entra a https://www.enterprisedb.com/downloads/postgres-postgresql-downloads'
        Write-Host "  2. En la fila que empieza con $versionServidor, baja el instalador de Windows x86-64."
        Write-Host '  3. Al instalar, deja marcado SOLO "Command Line Tools".'
        Write-Host '  4. Vuelve a correr este script. Va a tomar la version nueva solo.'
        Write-Host ''
        Write-Host 'No hay que desinstalar la version vieja: pueden convivir.'
        Write-Registro -Carpeta $carpetaRespaldos -Mensaje "FALLO: falta pg_dump $versionServidor (servidor mas nuevo que la herramienta)"
    } else {
        Write-Host 'Revisa el mensaje de arriba. Causas comunes: sin internet, o la URL del .env cambio.'
        Write-Registro -Carpeta $carpetaRespaldos -Mensaje 'FALLO: pg_dump termino con error'
    }

    # Un archivo a medias es una trampa: parece un respaldo y no lo es.
    if (Test-Path $destino) { Remove-Item $destino -Force }
    Salir 1
}

$tamano = [math]::Round((Get-Item $destino).Length / 1MB, 2)

# --- 4. Comprobar lo que se acaba de crear ---
#
# Que pg_dump termine sin error no garantiza un archivo útil: puede quedar cortado, o
# salir vacío si la conexión apuntaba a una base que no es. Aquí se abre el archivo y se
# revisa que estén las tablas que importan, ANTES de darlo por bueno.
Write-Host 'Comprobando el archivo...'
try {
    $tablas = Get-TablasDelRespaldo -PgRestore $pgRestore -Archivo $destino
} catch {
    Write-Host ''
    Write-Host 'El respaldo se creo pero NO SE PUEDE LEER.' -ForegroundColor Red
    Write-Host $_.Exception.Message
    Write-Registro -Carpeta $carpetaRespaldos -Mensaje "FALLO: liga_$fecha.dump ilegible"
    Salir 1
}

$faltantes = $script:TablasEsperadas | Where-Object { $tablas -notcontains $_ }
if ($faltantes) {
    Write-Host ''
    Write-Host 'ATENCION: el respaldo no trae estas tablas:' -ForegroundColor Red
    Write-Host ('  ' + ($faltantes -join ', '))
    Write-Host 'Puede que la URL del .env apunte a otra base. NO confies en este archivo.'
    Write-Registro -Carpeta $carpetaRespaldos -Mensaje "FALLO: liga_$fecha.dump sin $($faltantes -join ',')"
    Salir 1
}

Write-Host ''
Write-Host "Listo: liga_$fecha.dump ($tamano MB, $($tablas.Count) tablas)" -ForegroundColor Green
Write-Registro -Carpeta $carpetaRespaldos -Mensaje "OK: liga_$fecha.dump ($tamano MB, $($tablas.Count) tablas)"

# --- 5. Limpiar respaldos de más de 90 días ---
$viejos = Get-ChildItem $carpetaRespaldos -Filter 'liga_*.dump' |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-90) }
if ($viejos) {
    $viejos | Remove-Item
    Write-Host "Se borraron $($viejos.Count) respaldo(s) con mas de 90 dias."
}

$total = (Get-ChildItem $carpetaRespaldos -Filter 'liga_*.dump').Count
Write-Host "Respaldos guardados en la carpeta: $total"

if (-not $Silencioso) {
    Write-Host ''
    Write-Host 'Cada tanto conviene correr verificar_respaldo.bat: restaura el ultimo' -ForegroundColor Yellow
    Write-Host 'respaldo en una base aparte y cuenta lo que trae. Tener respaldos que'
    Write-Host 'nunca se probaron es casi lo mismo que no tenerlos.'
    Write-Host ''
}

Salir 0
