// Dibuja la imagen del resultado (1080x1080) en el canvas de partido_imagen.php y
// prepara el botón de descarga. Vive en un archivo aparte porque el CSP del sitio
// no permite <script> inline.
document.addEventListener('DOMContentLoaded', function () {
    var datos = document.getElementById('datosResultado');
    var canvas = document.getElementById('canvasResultado');
    if (!datos || !canvas) {
        return;
    }
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;
    var d = datos.dataset;

    // Carga una imagen y resuelve null si falla o no hay URL (equipo sin logo)
    var cargarImagen = function (url) {
        return new Promise(function (resolve) {
            if (!url) { resolve(null); return; }
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = url;
        });
    };

    var iniciales = function (nombre) {
        var partes = nombre.trim().split(/\s+/);
        if (partes.length === 1) { return partes[0].substring(0, 2).toUpperCase(); }
        return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
    };

    // Logo circular (imagen recortada en círculo, o círculo de color con iniciales)
    var dibujarLogo = function (img, x, y, radio, color, nombre) {
        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, radio, 0, Math.PI * 2);
        ctx.closePath();
        if (img) {
            ctx.clip();
            ctx.drawImage(img, x - radio, y - radio, radio * 2, radio * 2);
        } else {
            ctx.fillStyle = color;
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 ' + Math.round(radio * 0.72) + 'px Poppins, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(iniciales(nombre), x, y + radio * 0.04);
        }
        ctx.restore();
        // Aro blanco sutil alrededor
        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, radio, 0, Math.PI * 2);
        ctx.lineWidth = 6;
        ctx.strokeStyle = 'rgba(255,255,255,.65)';
        ctx.stroke();
        ctx.restore();
    };

    // Ajusta el tamaño de fuente para que un texto quepa en un ancho dado
    var fuenteQueQuepa = function (texto, pesoYFamilia, maxPx, anchoMax) {
        var px = maxPx;
        do {
            ctx.font = '700 ' + px + 'px ' + pesoYFamilia;
            if (ctx.measureText(texto).width <= anchoMax) { break; }
            px -= 2;
        } while (px > 20);
        return px;
    };

    var dibujar = function (logoLocal, logoVisit) {
        // Fondo: degradado oscuro con los colores de la copa
        var grad = ctx.createLinearGradient(0, 0, W, H);
        grad.addColorStop(0, '#171028');
        grad.addColorStop(1, '#241a3a');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        // Franja superior con el color primario de la copa
        var franja = ctx.createLinearGradient(0, 0, W, 0);
        franja.addColorStop(0, d.color1);
        franja.addColorStop(1, d.color2);
        ctx.fillStyle = franja;
        ctx.fillRect(0, 0, W, 14);

        // Nombre del torneo y temporada
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = 'rgba(255,255,255,.85)';
        var pxTorneo = fuenteQueQuepa(d.torneo.toUpperCase(), 'Poppins, Arial, sans-serif', 44, W - 140);
        ctx.font = '700 ' + pxTorneo + 'px Poppins, Arial, sans-serif';
        ctx.fillText(d.torneo.toUpperCase(), W / 2, 108);
        if (d.temporada) {
            ctx.fillStyle = 'rgba(255,255,255,.5)';
            ctx.font = '600 30px Inter, Arial, sans-serif';
            ctx.fillText('Temporada ' + d.temporada, W / 2, 156);
        }

        // Etiqueta RESULTADO FINAL
        ctx.fillStyle = d.color2;
        ctx.font = '700 34px Poppins, Arial, sans-serif';
        ctx.fillText('RESULTADO FINAL', W / 2, 250);

        // Logos y nombres de los equipos (fila propia, arriba); el marcador va DEBAJO en
        // su propia línea — antes se dibujaba a la misma altura y el número gigante se
        // encimaba sobre los logos.
        var yLogos = 430, radio = 120;
        var xLocal = 280, xVisit = W - 280;
        dibujarLogo(logoLocal, xLocal, yLogos, radio, d.localColor, d.localNombre);
        dibujarLogo(logoVisit, xVisit, yLogos, radio, d.visitColor, d.visitNombre);

        // "VS" pequeño entre los logos (en su fila, sin invadirlos)
        ctx.fillStyle = 'rgba(255,255,255,.45)';
        ctx.font = '800 44px Poppins, Arial, sans-serif';
        ctx.fillText('VS', W / 2, yLogos + 16);

        ctx.fillStyle = '#ffffff';
        var pxL = fuenteQueQuepa(d.localNombre, 'Poppins, Arial, sans-serif', 40, 440);
        ctx.font = '700 ' + pxL + 'px Poppins, Arial, sans-serif';
        ctx.fillText(d.localNombre, xLocal, yLogos + radio + 64);
        var pxV = fuenteQueQuepa(d.visitNombre, 'Poppins, Arial, sans-serif', 40, 440);
        ctx.font = '700 ' + pxV + 'px Poppins, Arial, sans-serif';
        ctx.fillText(d.visitNombre, xVisit, yLogos + radio + 64);

        // Marcador gigante en su propia línea, centrado bajo los nombres
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 170px Poppins, Arial, sans-serif';
        ctx.fillText(d.marcadorLocal + ' - ' + d.marcadorVisit, W / 2, 830);

        // Fecha y cancha
        ctx.fillStyle = 'rgba(255,255,255,.6)';
        ctx.font = '500 32px Inter, Arial, sans-serif';
        var linea = d.fecha + (d.cancha ? '  ·  ' + d.cancha : '');
        ctx.fillText(linea, W / 2, 930);

        // Pie con la marca
        ctx.fillStyle = 'rgba(255,255,255,.35)';
        ctx.font = '500 26px Inter, Arial, sans-serif';
        ctx.fillText('MJ Control Systems', W / 2, H - 60);

        // Franja inferior espejo de la superior
        ctx.fillStyle = franja;
        ctx.fillRect(0, H - 14, W, 14);

        // Botón de descarga con nombre de archivo descriptivo
        var btn = document.getElementById('btnDescargarImagen');
        if (btn) {
            btn.href = canvas.toDataURL('image/png');
            var limpio = function (t) { return t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); };
            btn.download = 'resultado-' + limpio(d.localNombre) + '-vs-' + limpio(d.visitNombre) + '.png';
        }
    };

    // Espera a que las fuentes del sitio estén listas para que el canvas use Poppins
    // real y no una fuente de reemplazo del sistema.
    var fuentesListas = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    Promise.all([fuentesListas, cargarImagen(d.localLogo), cargarImagen(d.visitLogo)])
        .then(function (res) { dibujar(res[1], res[2]); });
});
