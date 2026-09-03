# Prueba de restauración: comprueba que un respaldo SIRVE de verdad.
#
# Por qué existe
# -------------
# Tener respaldos no es lo mismo que poder restaurarlos. Un archivo puede pesar lo
# esperado, tener buena fecha y estar cortado por la mitad; o traer las tablas vacías
# porque el día que se hizo la conexión apuntaba a otro lado. Eso no se descubre nunca,
# hasta el día que hace falta — que es justo el peor día para descubrirlo.
#
# Qué hace
# --------
#   1. Toma el respaldo más reciente (o el que se le pase con -Archivo).
#   2. Lo DESCOMPRIME entero a un archivo temporal. Esto lee cada bloque de datos: si el
#      archivo está cortado o corrupto, aquí falla.
#   3. Cuenta las filas de cada tabla y avisa si alguna importante viene vacía.
#   4. Borra el temporal.
#
# NUNCA toca la base de producción: no se conecta a ningún servidor. Todo el trabajo es
# sobre el archivo, así que se puede correr a mitad de la temporada sin ningún riesgo.
#
# Doble clic en verificar_respaldo.bat, o a mano:
#   powershell -ExecutionPolicy Bypass -File herramientas\verificar_respaldo.ps1

param(
    [string]$Archivo,
    [switch]$Silencioso
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot '_comun.ps1')

$raiz = Split-Path -Parent $PSScriptRoot
$carpetaRespaldos = Join-Path $raiz 'Respaldos BD'

function Salir {
    param([int]$Codigo)
    if (-not $Silencioso) {
        Read-Host 'Presiona Enter para cerrar'
    }
    exit $Codigo
}

Write-Host ''
Write-Host '=== Prueba de restauracion ===' -ForegroundColor Cyan
Write-Host 'No se conecta a la base de la liga: todo el trabajo es sobre el archivo.'
Write-Host ''

# --- 1. Qué archivo se revisa ---
if (-not $Archivo) {
    $ultimo = Get-ChildItem $carpetaRespaldos -Filter 'liga_*.dump' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $ultimo) {
        Write-Host 'No hay ningun respaldo en "Respaldos BD".' -ForegroundColor Red
        Write-Host 'Corre primero respaldar_base.bat.'
        Salir 1
    }
    $Archivo = $ultimo.FullName
}

if (-not (Test-Path $Archivo)) {
    Write-Host "No existe el archivo: $Archivo" -ForegroundColor Red
    Salir 1
}

$info = Get-Item $Archivo
$dias = [math]::Round(((Get-Date) - $info.LastWriteTime).TotalDays)
Write-Host "Archivo:  $($info.Name)"
Write-Host "Fecha:    $($info.LastWriteTime.ToString('yyyy-MM-dd HH:mm')) ($dias dias)"
Write-Host "Tamano:   $([math]::Round($info.Length / 1MB, 2)) MB"
Write-Host ''

$pgRestore = Get-HerramientaPg -Nombre 'pg_restore'
if (-not $pgRestore) {
    Write-InstruccionesPostgres
    Salir 1
}

# --- 2. Descomprimir entero ---
#
# Se convierte el respaldo a SQL plano. Es la prueba de fuego del archivo: pg_restore
# tiene que leer y descomprimir TODOS los bloques de datos para escribirlos. Un archivo
# cortado o corrupto no llega al final.
$temporal = Join-Path $env:TEMP ("liga_verificacion_" + [System.Guid]::NewGuid().ToString('N') + '.sql')
Write-Host 'Restaurando el respaldo completo a un archivo temporal...'

try {
    & $pgRestore --no-owner --no-privileges --file=$temporal $Archivo 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $temporal)) {
        Write-Host ''
        Write-Host 'NO SE PUDO RESTAURAR ESTE RESPALDO.' -ForegroundColor Red
        Write-Host 'El archivo esta dañado o incompleto. Corre respaldar_base.bat para'
        Write-Host 'generar uno nuevo, y revisa si los anteriores tambien fallan.'
        Write-Registro -Carpeta $carpetaRespaldos -Mensaje "VERIFICACION FALLIDA: $($info.Name) no se puede restaurar"
        Salir 1
    }

    # --- 3. Contar lo que trae ---
    #
    # En el SQL, los datos de cada tabla vienen en un bloque "COPY tabla ... FROM stdin;"
    # que termina en una línea con "\.". Contar esas líneas da las filas reales que se
    # restaurarían, que es el número que de verdad importa.
    $conteos = [ordered]@{}
    $lector = [System.IO.StreamReader]::new($temporal, [System.Text.Encoding]::UTF8)
    $tablaActual = $null
    $filas = 0
    try {
        while ($null -ne ($linea = $lector.ReadLine())) {
            if ($null -ne $tablaActual) {
                if ($linea -eq '\.') {
                    $conteos[$tablaActual] = $filas
                    $tablaActual = $null
                    $filas = 0
                } else {
                    $filas++
                }
                continue
            }
            if ($linea -match '^COPY\s+(?:[A-Za-z0-9_]+\.)?"?([A-Za-z0-9_]+)"?\s*\(') {
                $tablaActual = $Matches[1]
                $filas = 0
            }
        }
    } finally {
        $lector.Close()
    }
} finally {
    if (Test-Path $temporal) { Remove-Item $temporal -Force -ErrorAction SilentlyContinue }
}

Write-Host 'Restaurado sin errores.' -ForegroundColor Green
Write-Host ''
Write-Host 'Lo que trae este respaldo:'
Write-Host ''

$anchoNombre = 22
foreach ($tabla in ($conteos.Keys | Sort-Object)) {
    $marca = ' '
    if ($script:TablasEsperadas -contains $tabla) {
        $marca = if ($conteos[$tabla] -gt 0) { '+' } else { '!' }
    }
    $nombre = $tabla.PadRight($anchoNombre)
    $color = if ($marca -eq '!') { 'Red' } else { 'Gray' }
    Write-Host ("  {0} {1} {2,7} filas" -f $marca, $nombre, $conteos[$tabla]) -ForegroundColor $color
}

# --- 4. Veredicto ---
$problemas = @()
foreach ($tabla in $script:TablasEsperadas) {
    if (-not $conteos.Contains($tabla)) {
        $problemas += "$tabla no viene en el respaldo"
    } elseif ($conteos[$tabla] -le 0) {
        $problemas += "$tabla viene vacia"
    }
}

Write-Host ''
if ($problemas.Count -gt 0) {
    Write-Host 'HAY PROBLEMAS:' -ForegroundColor Red
    foreach ($p in $problemas) { Write-Host "  - $p" -ForegroundColor Red }
    Write-Host ''
    Write-Host 'Si la liga ya tiene datos cargados, esto significa que el respaldo no'
    Write-Host 'sirve para restaurar. Revisa que DATABASE_URL en el .env apunte a la base'
    Write-Host 'correcta y genera uno nuevo.'
    Write-Registro -Carpeta $carpetaRespaldos -Mensaje "VERIFICACION CON PROBLEMAS: $($info.Name) — $($problemas -join '; ')"
    Salir 1
}

Write-Host 'ESTE RESPALDO SIRVE.' -ForegroundColor Green
Write-Host 'Se restauro completo y todas las tablas importantes traen datos.'
Write-Registro -Carpeta $carpetaRespaldos -Mensaje "VERIFICACION OK: $($info.Name) restaurado y con datos en todas las tablas clave"

if (-not $Silencioso) {
    Write-Host ''
    Write-Host 'Si algun dia hay que restaurar de verdad, el comando es:' -ForegroundColor Yellow
    Write-Host "  pg_restore --clean --no-owner -d `"URL_DE_LA_BASE`" `"$($info.Name)`""
    Write-Host 'Eso REEMPLAZA lo que haya en la base de destino. Ante la duda, pregunta antes.'
    Write-Host ''
}

Salir 0
