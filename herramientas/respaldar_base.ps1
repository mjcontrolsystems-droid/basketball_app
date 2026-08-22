# Respaldo de la base de datos de la plataforma (Neon PostgreSQL).
#
# Qué hace, en orden:
#   1. Lee DATABASE_URL del archivo .env del proyecto (no hay que pegar credenciales aquí).
#   2. Le quita el "-pooler" al host: pg_dump necesita la conexión directa, no la del
#      pooler — a través del pooler el respaldo puede fallar a mitad de camino.
#   3. Genera un respaldo comprimido y fechado en la carpeta "Respaldos BD".
#   4. Borra los respaldos con más de 90 días, para que la carpeta no crezca sin fin.
#
# Se ejecuta con doble clic en respaldar_base.bat, o a mano:
#   powershell -ExecutionPolicy Bypass -File herramientas\respaldar_base.ps1

$ErrorActionPreference = 'Stop'

# --- Rutas ---
$raiz = Split-Path -Parent $PSScriptRoot           # carpeta del proyecto
$archivoEnv = Join-Path $raiz '.env'
$carpetaRespaldos = Join-Path $raiz 'Respaldos BD'
$fecha = Get-Date -Format 'yyyy-MM-dd_HHmm'
$destino = Join-Path $carpetaRespaldos "liga_$fecha.dump"

Write-Host ''
Write-Host '=== Respaldo de la base de datos ===' -ForegroundColor Cyan

# --- 1. pg_dump instalado? ---
$pgDump = Get-Command pg_dump -ErrorAction SilentlyContinue
if (-not $pgDump) {
    # Buscarlo en las rutas típicas del instalador de PostgreSQL en Windows,
    # porque el instalador no siempre lo agrega al PATH.
    $candidatos = Get-ChildItem 'C:\Program Files\PostgreSQL\*\bin\pg_dump.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending
    if ($candidatos) {
        $pgDump = $candidatos[0].FullName
    } else {
        Write-Host ''
        Write-Host 'No se encontró pg_dump.' -ForegroundColor Red
        Write-Host 'Instálalo así (una sola vez):'
        Write-Host '  1. Entra a https://www.enterprisedb.com/downloads/postgres-postgresql-downloads'
        Write-Host '  2. Descarga el instalador de Windows de la versión 17.'
        Write-Host '  3. Al instalar, deja marcado SOLO "Command Line Tools" (lo demás no hace falta).'
        Write-Host '  4. Vuelve a correr este script.'
        Read-Host 'Presiona Enter para cerrar'
        exit 1
    }
} else {
    $pgDump = $pgDump.Source
}

# --- 2. Leer DATABASE_URL del .env ---
if (-not (Test-Path $archivoEnv)) {
    Write-Host "No existe el archivo .env en $raiz" -ForegroundColor Red
    Write-Host 'Este script lee de ahí la conexión a la base; sin él no sabe a dónde conectarse.'
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}

$lineaUrl = Get-Content $archivoEnv | Where-Object { $_ -match '^\s*DATABASE_URL\s*=' } | Select-Object -First 1
if (-not $lineaUrl) {
    Write-Host 'El .env no tiene DATABASE_URL.' -ForegroundColor Red
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}
$url = ($lineaUrl -split '=', 2)[1].Trim().Trim('"').Trim("'")

# --- 3. Conexión directa, sin el pooler ---
$urlDirecta = $url -replace '-pooler\.', '.'
if ($url -ne $urlDirecta) {
    Write-Host 'Usando la conexión directa (sin -pooler), que es la que pg_dump necesita.'
}

# --- 4. Generar el respaldo ---
New-Item -ItemType Directory -Force -Path $carpetaRespaldos | Out-Null
Write-Host "Respaldando hacia: $destino"
Write-Host 'Esto tarda unos segundos...'

& $pgDump --format=custom --no-owner --no-privileges --file=$destino $urlDirecta
if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host 'El respaldo FALLÓ. Revisa el mensaje de arriba.' -ForegroundColor Red
    Write-Host 'Causas comunes: sin internet, o la URL del .env cambió.'
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}

$tamano = [math]::Round((Get-Item $destino).Length / 1MB, 2)
Write-Host ''
Write-Host "Listo: liga_$fecha.dump ($tamano MB)" -ForegroundColor Green

# --- 5. Limpiar respaldos de más de 90 días ---
$viejos = Get-ChildItem $carpetaRespaldos -Filter 'liga_*.dump' |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-90) }
if ($viejos) {
    $viejos | Remove-Item
    Write-Host "Se borraron $($viejos.Count) respaldo(s) con más de 90 días."
}

$total = (Get-ChildItem $carpetaRespaldos -Filter 'liga_*.dump').Count
Write-Host "Respaldos guardados en la carpeta: $total"
Write-Host ''
Write-Host 'Para RESTAURAR un respaldo (solo si de verdad hace falta):' -ForegroundColor Yellow
Write-Host '  pg_restore --clean --no-owner -d "URL_DE_LA_BASE" "archivo.dump"'
Write-Host 'Eso REEMPLAZA lo que haya en la base. Ante la duda, pregunta antes de correrlo.'
Write-Host ''
Read-Host 'Presiona Enter para cerrar'
