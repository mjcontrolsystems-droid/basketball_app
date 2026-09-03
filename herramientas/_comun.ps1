# Piezas que comparten los scripts de respaldo.
#
# Estaban copiadas en cada uno. Con tres scripts (respaldar, verificar, programar) la
# copia se vuelve un problema: se arregla la búsqueda de pg_dump en uno y los otros dos
# se quedan con el error viejo.
#
# Se carga con:  . (Join-Path $PSScriptRoot '_comun.ps1')

# Las tablas sin las cuales un respaldo no sirve de nada. Se comprueban al crear el
# archivo y al verificarlo: un respaldo "exitoso" que no traiga los partidos no es un
# respaldo, y sin esta lista nadie se daría cuenta hasta el día que haga falta.
$script:TablasEsperadas = @(
    'torneos', 'equipos', 'jugadores', 'partidos', 'partido_eventos',
    'sanciones', 'usuarios', 'colaboradores'
)

<#
.SYNOPSIS
Ruta de una herramienta de PostgreSQL (pg_dump, pg_restore, psql).

.DESCRIPTION
Se busca primero en Program Files y se toma la versión MÁS NUEVA instalada, no la del
PATH: estas herramientas se niegan a trabajar contra un servidor más nuevo que ellas
("server version mismatch"), y el PATH suele apuntar a la versión vieja aunque ya esté
instalada la nueva. El número de carpeta se compara como número (18 > 9), no como texto.
#>
function Get-HerramientaPg {
    param([Parameter(Mandatory = $true)][string]$Nombre)

    $candidatos = Get-ChildItem "C:\Program Files\PostgreSQL\*\bin\$Nombre.exe" -ErrorAction SilentlyContinue |
        Sort-Object { [int]($_.FullName -replace '.*PostgreSQL\\(\d+).*', '$1') } -Descending
    if ($candidatos) {
        return $candidatos[0].FullName
    }

    $enPath = Get-Command $Nombre -ErrorAction SilentlyContinue
    if ($enPath) {
        return $enPath.Source
    }

    return $null
}

function Write-InstruccionesPostgres {
    Write-Host ''
    Write-Host 'No se encontraron las herramientas de PostgreSQL.' -ForegroundColor Red
    Write-Host 'Se instalan una sola vez:'
    Write-Host '  1. Entra a https://www.enterprisedb.com/downloads/postgres-postgresql-downloads'
    Write-Host '  2. Descarga el instalador de Windows de la version 18 (o la mas nueva).'
    Write-Host '  3. Al instalar, deja marcado SOLO "Command Line Tools".'
    Write-Host '  4. Vuelve a correr este script.'
}

<#
.SYNOPSIS
La URL de conexión a la base, leída del .env del proyecto.

.DESCRIPTION
Devuelve la conexión DIRECTA (sin "-pooler" en el host). pg_dump y pg_restore la
necesitan así: a través del pooler el trabajo puede cortarse a mitad de camino.

Las credenciales nunca se escriben en estos scripts — salen del .env, que está fuera
del control de versiones.
#>
function Get-UrlBase {
    param([Parameter(Mandatory = $true)][string]$Raiz)

    $archivoEnv = Join-Path $Raiz '.env'
    if (-not (Test-Path $archivoEnv)) {
        throw "No existe el archivo .env en $Raiz. De ahi se lee la conexion a la base."
    }

    $linea = Get-Content $archivoEnv | Where-Object { $_ -match '^\s*DATABASE_URL\s*=' } | Select-Object -First 1
    if (-not $linea) {
        throw 'El .env no tiene DATABASE_URL.'
    }

    $url = ($linea -split '=', 2)[1].Trim().Trim('"').Trim("'")

    return ($url -replace '-pooler\.', '.')
}

<#
.SYNOPSIS
Deja constancia de cada corrida en "Respaldos BD\registro.txt".

.DESCRIPTION
Cuando el respaldo corre solo (tarea programada) nadie ve la pantalla. Sin este registro,
un respaldo que lleva tres semanas fallando se ve exactamente igual que uno que funciona:
no se ve nada. El archivo se revisa de un vistazo y dice qué pasó cada día.
#>
function Write-Registro {
    param(
        [Parameter(Mandatory = $true)][string]$Carpeta,
        [Parameter(Mandatory = $true)][string]$Mensaje
    )

    New-Item -ItemType Directory -Force -Path $Carpeta | Out-Null
    $linea = '{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm'), $Mensaje
    Add-Content -Path (Join-Path $Carpeta 'registro.txt') -Value $linea -Encoding UTF8
}

<#
.SYNOPSIS
Lee el índice de un respaldo y devuelve las tablas que trae.

.DESCRIPTION
"pg_restore --list" abre el archivo y lee su índice sin restaurar nada. Es la forma
barata de comprobar que el archivo es un respaldo de verdad y no medio archivo que se
cortó cuando se cayó el internet.
#>
function Get-TablasDelRespaldo {
    param(
        [Parameter(Mandatory = $true)][string]$PgRestore,
        [Parameter(Mandatory = $true)][string]$Archivo
    )

    $indice = & $PgRestore --list $Archivo 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "El archivo no se puede leer como respaldo. Detalle: $indice"
    }

    $tablas = @()
    foreach ($linea in $indice) {
        # Las líneas de datos se ven así:
        #   1234; 0 0 TABLE DATA public partidos usuario
        if ("$linea" -match 'TABLE DATA\s+\S+\s+(\S+)') {
            $tablas += $Matches[1]
        }
    }

    return ($tablas | Sort-Object -Unique)
}
