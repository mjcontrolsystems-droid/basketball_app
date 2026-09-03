# Deja el respaldo corriendo solo, todos los días.
#
# Por qué
# -------
# El respaldo manual funciona, pero depende de que alguien se acuerde. En temporada, la
# semana que más pasa es justo la que menos tiempo hay para acordarse. Esto registra una
# tarea de Windows que lo hace sola.
#
# Detalles que importan:
#   - Corre a las 20:00, cuando la computadora ya suele estar encendida y el día de
#     partidos ya terminó de capturarse.
#   - Si la computadora estaba apagada a esa hora, la tarea se pone al día en cuanto se
#     encienda (-StartWhenAvailable). Sin eso, apagar la máquina un domingo significaba
#     saltarse el respaldo del día que más datos nuevos hubo.
#   - Corre en segundo plano y sin ventana, con -Silencioso, para que no interrumpa.
#   - Cada corrida queda anotada en "Respaldos BD\registro.txt".
#
# Doble clic en programar_respaldo.bat. Para quitarla: -Quitar

param(
    [switch]$Quitar,
    [string]$Hora = '20:00'
)

$ErrorActionPreference = 'Stop'

$nombreTarea = 'Respaldo base de datos - Liga MJ Control Systems'
$script = Join-Path $PSScriptRoot 'respaldar_base.ps1'

Write-Host ''
Write-Host '=== Respaldo automatico ===' -ForegroundColor Cyan

# --- Quitar ---
if ($Quitar) {
    $existente = Get-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue
    if (-not $existente) {
        Write-Host 'No habia ninguna tarea programada.' -ForegroundColor Yellow
    } else {
        Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false
        Write-Host 'Tarea quitada. El respaldo ya no corre solo.' -ForegroundColor Green
        Write-Host 'Puedes seguir respaldando a mano con respaldar_base.bat.'
    }
    Write-Host ''
    Read-Host 'Presiona Enter para cerrar'
    exit 0
}

if (-not (Test-Path $script)) {
    Write-Host "No se encontro respaldar_base.ps1 junto a este archivo." -ForegroundColor Red
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}

# --- Registrar ---
#
# -WindowStyle Hidden y -NonInteractive: nadie va a estar ahí para ver la ventana ni para
# contestar nada. El resultado se lee después en el registro.
$accion = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -Silencioso' -f $script)

$disparador = New-ScheduledTaskTrigger -Daily -At $Hora

$opciones = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -DontStopIfGoingOnBatteries `
    -AllowStartIfOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

try {
    Register-ScheduledTask `
        -TaskName $nombreTarea `
        -Action $accion `
        -Trigger $disparador `
        -Settings $opciones `
        -Description 'Respalda la base de la liga y comprueba que el archivo sirva. Lo instalo herramientas\programar_respaldo.bat.' `
        -Force | Out-Null
} catch {
    Write-Host ''
    Write-Host 'No se pudo registrar la tarea.' -ForegroundColor Red
    Write-Host $_.Exception.Message
    Write-Host ''
    Write-Host 'Si dice que hace falta permiso, cierra esta ventana, haz clic DERECHO sobre'
    Write-Host 'programar_respaldo.bat y elige "Ejecutar como administrador".'
    Read-Host 'Presiona Enter para cerrar'
    exit 1
}

Write-Host ''
Write-Host "Listo. El respaldo va a correr solo todos los dias a las $Hora." -ForegroundColor Green
Write-Host 'Si la computadora esta apagada a esa hora, corre en cuanto la enciendas.'
Write-Host ''
Write-Host 'Donde ver que esta funcionando:'
Write-Host '  - Los archivos: carpeta "Respaldos BD" del proyecto.'
Write-Host '  - El detalle de cada corrida: "Respaldos BD\registro.txt".'
Write-Host ''
Write-Host 'Cada cierto tiempo corre verificar_respaldo.bat: comprueba que el ultimo' -ForegroundColor Yellow
Write-Host 'respaldo se pueda restaurar de verdad, no solo que el archivo exista.'
Write-Host ''
Write-Host 'Para quitarla:  programar_respaldo.bat quitar'
Write-Host ''
Read-Host 'Presiona Enter para cerrar'
exit 0
