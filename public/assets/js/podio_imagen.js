// Dibuja la imagen del podio (1080x1080) en el canvas de podio_imagen.php y prepara el
// botón de descarga. Vive en un archivo aparte porque el CSP del sitio no permite
// <script> inline, igual que partido_imagen.js.
document.addEventListener('DOMContentLoaded', function () {
    var datos = document.getElementById('datosPodio');
    var canvas = document.getElementById('canvasPodio');
    if (!datos || !canvas) {
        return;
    }
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;
    var d = datos.dataset;

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
        var partes = (nombre || '').trim().split(/\s+/);
        if (partes.length === 0 || partes[0] === '') { return '?'; }
        if (partes.length === 1) { return partes[0].substring(0, 2).toUpperCase(); }
        return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
    };

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
        ctx.save();
        ctx.beginPath();
        ctx.arc(x, y, radio, 0, Math.PI * 2);
        ctx.lineWidth = 6;
        ctx.strokeStyle = 'rgba(255,255,255,.65)';
        ctx.stroke();
        ctx.restore();
    };

    // Baja el tamaño de fuente hasta que el texto quepa en el ancho dado. Un nombre de
    // equipo largo se saldría del cuadro y arruinaría la imagen.
    var textoAjustado = function (texto, x, y, anchoMax, tamanoBase, peso) {
        var tamano = tamanoBase;
        ctx.font = peso + ' ' + tamano + 'px Poppins, Arial, sans-serif';
        while (ctx.measureText(texto).width > anchoMax && tamano > 18) {
            tamano -= 2;
            ctx.font = peso + ' ' + tamano + 'px Poppins, Arial, sans-serif';
        }
        ctx.fillText(texto, x, y);
    };

    var puestos = [
        // orden de dibujo: el campeón va al centro y más grande
        { n: 2, x: W * 0.20, yLogo: 660, radio: 92, medalla: '🥈', tamNombre: 34 },
        { n: 3, x: W * 0.80, yLogo: 660, radio: 92, medalla: '🥉', tamNombre: 34 },
        { n: 1, x: W * 0.50, yLogo: 520, radio: 130, medalla: '🥇', tamNombre: 46 }
    ];

    Promise.all(puestos.map(function (p) { return cargarImagen(d['eq' + p.n + 'Logo']); })).then(function (imgs) {
        // Fondo con el degradado de la copa
        var grad = ctx.createLinearGradient(0, 0, W, H);
        grad.addColorStop(0, d.color1 || '#241a3a');
        grad.addColorStop(1, d.color2 || '#7b2ff7');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';

        // Encabezado
        ctx.fillStyle = 'rgba(255,255,255,.75)';
        ctx.font = '600 30px Poppins, Arial, sans-serif';
        ctx.fillText((d.temporada || '').toUpperCase() + ' · FINALIZADA', W / 2, 110);

        ctx.fillStyle = '#ffffff';
        textoAjustado(d.torneo || '', W / 2, 190, W - 140, 62, '700');

        ctx.fillStyle = 'rgba(255,255,255,.9)';
        ctx.font = '600 40px Poppins, Arial, sans-serif';
        ctx.fillText('¡Felicidades!', W / 2, 262);

        puestos.forEach(function (p, i) {
            var nombre = d['eq' + p.n + 'Nombre'];
            if (!nombre) { return; }

            dibujarLogo(imgs[i], p.x, p.yLogo, p.radio, d['eq' + p.n + 'Color'] || '#7b2ff7', nombre);

            // Medalla justo encima del logo
            ctx.font = Math.round(p.radio * 0.62) + 'px Arial, sans-serif';
            ctx.fillText(p.medalla, p.x, p.yLogo - p.radio - 18);

            // Puesto y nombre debajo
            ctx.fillStyle = 'rgba(255,255,255,.75)';
            ctx.font = '600 24px Poppins, Arial, sans-serif';
            ctx.fillText((d['titulo' + p.n] || '').toUpperCase(), p.x, p.yLogo + p.radio + 52);

            ctx.fillStyle = '#ffffff';
            // El ancho disponible del campeón es mayor porque va solo en su fila
            textoAjustado(nombre, p.x, p.yLogo + p.radio + 100, p.n === 1 ? W - 200 : W * 0.36, p.tamNombre, '700');
        });

        // Pie
        ctx.fillStyle = 'rgba(255,255,255,.55)';
        ctx.font = '500 24px Poppins, Arial, sans-serif';
        ctx.fillText('MJ Control System', W / 2, H - 50);

        var boton = document.getElementById('btnDescargarPodio');
        if (boton) {
            boton.href = canvas.toDataURL('image/png');
        }
    });
});
