#!/usr/bin/env bash
#
# Pruebas de extremo a extremo contra un sitio ya levantado.
#
# Recorren las pantallas públicas y las del panel, y ejercitan los flujos que ESCRIBEN
# (guardar equipo, alineación, eventos, tiempo extra, generar calendario, subir imagen).
# Complementan a scripts/tests.php, que solo cubre la lógica pura sin base de datos.
#
# Necesitan la base de PRUEBA con los datos de scripts/seed_pruebas.php cargados:
#
#   docker run -d --name copa-pg -e POSTGRES_PASSWORD=copa -e POSTGRES_USER=copa \
#     -e POSTGRES_DB=copa_test -p 55432:5432 postgres:16-alpine
#   docker exec -i copa-pg psql -U copa -d copa_test < schema.sql
#   export DATABASE_URL="postgresql://copa:copa@127.0.0.1:55432/copa_test?sslmode=disable"
#   php scripts/seed_pruebas.php
#   php -S 127.0.0.1:8100 -t public router.php &
#   scripts/pruebas_e2e.sh 8100
#
# Sale con código 0 si todo pasa, 1 si algo falla (útil para CI).

BASE="http://127.0.0.1:${1:-8100}"
GALLETAS=$(mktemp)
TMP=$(mktemp -d)
FALLOS=0
PRUEBAS=0

limpiar() { rm -rf "$GALLETAS" "$TMP"; }
trap limpiar EXIT

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------

# Los errores de PHP no se imprimen en producción (display_errors=0), así que además del
# código HTTP se revisa que no se haya colado ninguno en el cuerpo de la respuesta.
revisar_cuerpo() {
    grep -qiE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|Undefined (variable|index|array key)|Ocurrió un error inesperado" "$1"
}

resultado() {
    local ok="$1" desc="$2" detalle="$3"
    PRUEBAS=$((PRUEBAS + 1))
    if [ "$ok" = "si" ]; then
        printf '  OK   | %s\n' "$desc"
    else
        printf ' FALLA | %-52s %s\n' "$desc" "$detalle"
        FALLOS=$((FALLOS + 1))
    fi
}

# GET a una ruta, comprobando el código HTTP esperado (200 por defecto).
probar() {
    local ruta="$1" esperado="${2:-200}" codigo problema=""
    codigo=$(curl -s -b "$GALLETAS" -c "$GALLETAS" -o "$TMP/salida" -w "%{http_code}" "$BASE$ruta")
    [ "$codigo" != "$esperado" ] && problema="HTTP $codigo (esperaba $esperado)"
    revisar_cuerpo "$TMP/salida" && problema="$problema ERROR-PHP"
    [ -z "$problema" ] && resultado si "$ruta" || resultado no "$ruta" "$problema"
}

# Ruta de archivo tal como la entiende curl. En Git Bash (Windows) el curl instalado es
# una build nativa que NO resuelve rutas estilo /tmp/...: hay que traducirlas o el envío
# falla en silencio con código 000.
ruta_para_curl() {
    if command -v cygpath > /dev/null 2>&1; then cygpath -w "$1"; else printf '%s' "$1"; fi
}

# El token CSRF se toma de la propia página, igual que haría un navegador.
csrf_de() {
    curl -s -b "$GALLETAS" -c "$GALLETAS" "$BASE$1" \
        | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'
}

# POST de formulario normal.
postear() {
    local desc="$1" pagina="$2" datos="$3" esperado="${4:-302}" token codigo
    token=$(csrf_de "$pagina")
    if [ -z "$token" ]; then
        resultado no "$desc" "no se encontró token CSRF en $pagina"
        return
    fi
    codigo=$(curl -s -b "$GALLETAS" -c "$GALLETAS" -o "$TMP/salida" -w "%{http_code}" \
        -d "csrf_token=${token}&${datos}" "$BASE$pagina")
    [ "$codigo" = "$esperado" ] && resultado si "$desc" || resultado no "$desc" "HTTP $codigo (esperaba $esperado)"
}

# Comprueba que un texto aparezca en una página: confirma que la escritura tuvo efecto.
verificar() {
    local desc="$1" pagina="$2" texto="$3"
    if curl -s -b "$GALLETAS" -c "$GALLETAS" "$BASE$pagina" | grep -q "$texto"; then
        resultado si "$desc"
    else
        resultado no "$desc" "no aparece \"$texto\""
    fi
}

# ---------------------------------------------------------------------------
echo "== Sitio público =="
probar "/"
probar "/login.php"
probar "/registro.php"
probar "/olvide_password.php"
probar "/ruta-que-no-existe" 404
probar "/copa-que-no-existe/tabla.php" 404

echo ""
echo "== Liga Municipal (fútbol · formato LIGA · ida y vuelta · con logo) =="
for r in "" "/tabla.php" "/calendario.php" "/equipos.php" "/equipo.php?id=1" \
         "/partido.php?id=1" "/partido.php?id=1&imprimir=1" "/partido_vivo.php?id=2" \
         "/partido_vivo_datos.php?id=2" "/partido_imagen.php?id=1" \
         "/patrocinadores.php" "/organizador.php"; do
    probar "/liga-municipal$r"
done

echo ""
echo "== Copa Estrellas (basketball · formato CAMPEONATO · con playoffs) =="
for r in "" "/tabla.php" "/calendario.php" "/calendario.php?fase=semifinal" \
         "/equipos.php" "/equipo.php?id=5" "/partido.php?id=4" "/partido_vivo.php?id=4"; do
    probar "/copa-estrellas$r"
done

echo ""
echo "== Recursos y sesión cerrada =="
probar "/assets/css/style.css"
probar "/assets/js/app.js"
probar "/imagen.php?id=1"
probar "/admin/index.php" 302
probar "/admin/partidos.php" 302

echo ""
echo "== El código de la aplicación NO debe ser alcanzable por web =="
for r in "/app/Support/bd.php" "/app/Models/Torneo.php" "/config/config.php" "/schema.sql" "/.env"; do
    probar "$r" 404
done

# ---------------------------------------------------------------------------
echo ""
echo "== Panel del organizador =="
curl -s -c "$GALLETAS" "$BASE/login.php" > /dev/null
codigo=$(curl -s -b "$GALLETAS" -c "$GALLETAS" -o /dev/null -w "%{http_code}" \
    -d "usuario=prueba&password=prueba123" "$BASE/login.php")
if [ "$codigo" != "302" ]; then
    echo " FALLA | login devolvió $codigo (esperaba 302). Se cancela el resto."
    exit 1
fi
resultado si "login"
curl -s -b "$GALLETAS" -c "$GALLETAS" -o /dev/null "$BASE/admin/torneos.php?accion=entrar&id=1"

for r in "/admin/index.php" "/admin/torneos.php" "/admin/torneos.php?accion=nuevo" \
         "/admin/torneos.php?accion=editar&id=1" "/admin/equipos.php" \
         "/admin/equipos.php?accion=nuevo" "/admin/equipos.php?accion=editar&id=1" \
         "/admin/jugadores.php?equipo_id=1" "/admin/jugadores.php?equipo_id=1&accion=nuevo" \
         "/admin/partidos.php" "/admin/partidos.php?accion=nuevo" \
         "/admin/partidos.php?accion=editar&id=1" "/admin/partidos.php?accion=generar" \
         "/admin/partido_eventos.php?partido_id=1" "/admin/partido_eventos.php?partido_id=2" \
         "/admin/patrocinadores.php" "/admin/comentarios.php" "/admin/bitacora.php" \
         "/admin/perfil.php"; do
    probar "$r"
done

echo ""
echo "== Escrituras =="
postear "editar equipo" "/admin/equipos.php?accion=editar&id=1" \
    "accion=guardar&id=1&nombre=Deportivo+Norte&ciudad=Ciudad+Editada&sede=X&entrenador=DT&fundacion=1990&color_primario=%23c1121f&color_secundario=%23fdf0d5&descripcion=Editado"
verificar "el cambio del equipo se guardó" "/admin/equipos.php" "Ciudad Editada"

postear "agregar jugador" "/admin/jugadores.php?equipo_id=1&accion=nuevo" \
    "accion=guardar&equipo_id=1&id=0&dorsal=99&nombre=Nuevo+Jugador&posicion=delantero&activo=1"
verificar "el jugador aparece en la plantilla" "/admin/jugadores.php?equipo_id=1" "Nuevo Jugador"

postear "guardar alineación" "/admin/partido_eventos.php?partido_id=3" \
    "accion=guardar_alineacion&partido_id=3&titular%5B%5D=1&titular%5B%5D=2&titular%5B%5D=3&posicion%5B1%5D=portero&posicion%5B2%5D=defensa"
verificar "quedan 3 titulares marcados" "/admin/partido_eventos.php?partido_id=3" "3</span>/7 titulares"

postear "registrar gol" "/admin/partido_eventos.php?partido_id=3" \
    "accion=agregar_gol&partido_id=3&equipo_id=1&jugador_id=6&minuto=10&tipo_gol=jugada"
verificar "el gol entró en la ficha" "/admin/partido_eventos.php?partido_id=3" "Eventos cargados (1)"

postear "iniciar cronómetro" "/admin/partido_eventos.php?partido_id=3" \
    "accion=cronometro_iniciar&partido_id=3"
postear "agregar tiempo extra" "/admin/partido_eventos.php?partido_id=3" \
    "accion=cronometro_agregar_extra&partido_id=3&minutos=3"
verificar "el tiempo extra se refleja" "/admin/partido_eventos.php?partido_id=3" "3 min extra"

postear "marcar encuentro como jugado" "/admin/partidos.php" "accion=alternar_jugado&id=3"
postear "reabrir el encuentro" "/admin/partidos.php" "accion=alternar_jugado&id=3"

echo ""
echo "== Generador de calendario =="
# Esta liga tiene un encuentro jugado CON ficha: rehacer el calendario debe quedar
# bloqueado y no borrar nada. Es la red de seguridad, no un error.
postear "se niega a pisar resultados en firme" "/admin/partidos.php?accion=generar" \
    "accion=generar_fixture&vueltas=2&fecha_inicio=2026-09-01&hora=19%3A00&dias_entre_jornadas=7&cancha=A&reemplazar=1"
verificar "no borró los encuentros existentes" "/admin/partidos.php" "Encuentros (3)"

curl -s -b "$GALLETAS" -c "$GALLETAS" -o /dev/null "$BASE/admin/torneos.php?accion=entrar&id=2"
postear "reabrir el jugado de la copa" "/admin/partidos.php" "accion=alternar_jugado&id=4"
postear "generar (4 equipos, ida y vuelta)" "/admin/partidos.php?accion=generar" \
    "accion=generar_fixture&vueltas=2&fecha_inicio=2026-09-01&hora=19%3A00&dias_entre_jornadas=7&cancha=A&reemplazar=1"
# 12 de temporada regular + la semifinal, que el generador no toca
verificar "12 generados y la semifinal intacta" "/admin/partidos.php" "Encuentros (13)"

echo ""
echo "== Subida de imágenes =="
# Se genera un PNG de verdad: el servidor valida el tipo real del archivo (mime_content_type),
# así que no sirve mandar bytes cualquiera con nombre .png.
php -r '$i = imagecreatetruecolor(120, 120); imagefill($i, 0, 0, imagecolorallocate($i, 20, 122, 70)); imagepng($i, $argv[1]);' "$TMP/escudo.png" 2>/dev/null
if [ ! -s "$TMP/escudo.png" ]; then
    resultado no "generar PNG de prueba" "hace falta la extensión gd de PHP"
else
    curl -s -b "$GALLETAS" -c "$GALLETAS" -o /dev/null "$BASE/admin/torneos.php?accion=entrar&id=1"
    token=$(csrf_de "/admin/equipos.php?accion=editar&id=1")
    codigo=$(curl -s -b "$GALLETAS" -c "$GALLETAS" -o "$TMP/salida" -w "%{http_code}" \
        -F "csrf_token=${token}" -F "accion=guardar" -F "id=1" -F "nombre=Deportivo Norte" \
        -F "ciudad=Ciudad Editada" -F "sede=X" -F "entrenador=DT" -F "fundacion=1990" \
        -F "color_primario=#c1121f" -F "color_secundario=#fdf0d5" -F "descripcion=Con escudo" \
        -F "logo=@$(ruta_para_curl "$TMP/escudo.png");type=image/png" \
        "$BASE/admin/equipos.php?accion=editar&id=1")
    [ "$codigo" = "302" ] && resultado si "subir escudo del equipo" || resultado no "subir escudo del equipo" "HTTP $codigo"

    # El id queda en equipos.logo y se sirve por /imagen.php?id=N. Se busca dentro del
    # bloque de vista previa del escudo: en la página hay otras imágenes (el logo de la
    # copa en el sidebar) y quedarse con la primera daría un id equivocado.
    idImagen=$(curl -s -b "$GALLETAS" -c "$GALLETAS" "$BASE/admin/equipos.php?accion=editar&id=1" \
        | sed -n '/id="previewLogoEquipo"/,/<\/div>/p' \
        | grep -o 'imagen\.php?id=[0-9]*' | head -1 | grep -o '[0-9]*$')
    if [ -z "$idImagen" ]; then
        resultado no "el escudo quedó guardado" "no aparece ninguna imagen en la ficha del equipo"
    else
        resultado si "el escudo quedó guardado (imagen #$idImagen)"
        tipo=$(curl -s -o "$TMP/servida" -w "%{content_type}" "$BASE/imagen.php?id=$idImagen")
        peso=$(wc -c < "$TMP/servida")
        if [ "$tipo" = "image/png" ] && [ "$peso" -gt 100 ]; then
            resultado si "se sirve como PNG válido ($peso bytes)"
        else
            resultado no "se sirve como PNG válido" "content-type=$tipo peso=$peso"
        fi
        # Y debe verse en el sitio público, que es el objetivo de subirlo
        verificar "el escudo sale en el sitio público" "/liga-municipal/equipos.php" "imagen.php?id=$idImagen"
    fi

    # Un archivo que no es imagen tiene que rechazarse
    printf 'esto no es una imagen' > "$TMP/falso.png"
    token=$(csrf_de "/admin/equipos.php?accion=editar&id=2")
    curl -s -b "$GALLETAS" -c "$GALLETAS" -o /dev/null \
        -F "csrf_token=${token}" -F "accion=guardar" -F "id=2" -F "nombre=Atlético Sur" \
        -F "ciudad=C" -F "sede=X" -F "entrenador=DT" -F "fundacion=1991" \
        -F "color_primario=#003049" -F "color_secundario=#669bbc" -F "descripcion=d" \
        -F "logo=@$(ruta_para_curl "$TMP/falso.png");type=image/png" \
        "$BASE/admin/equipos.php?accion=editar&id=2"
    verificar "rechaza un archivo que no es imagen" "/admin/equipos.php" "Formato de imagen no permitido"
fi

# ---------------------------------------------------------------------------
echo ""
echo "==================================="
printf "%d pruebas, %d fallos\n" "$PRUEBAS" "$FALLOS"
if [ "$FALLOS" -eq 0 ]; then
    echo "TODAS LAS PRUEBAS PASARON"
else
    echo "HAY FALLOS - revisar antes de desplegar"
fi
exit $([ "$FALLOS" -eq 0 ] && echo 0 || echo 1)
