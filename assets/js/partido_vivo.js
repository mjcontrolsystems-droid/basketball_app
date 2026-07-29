(function () {
    var contenedor = document.getElementById('partidoVivo');
    if (!contenedor) {
        return;
    }

    var urlDatos = contenedor.getAttribute('data-url-datos');
    var basketball = contenedor.getAttribute('data-basketball') === '1';
    var marcadorLocalEl = document.getElementById('marcadorLocal');
    var marcadorVisitEl = document.getElementById('marcadorVisitante');
    var feedEl = document.getElementById('feedEventos');
    var estadoEl = document.getElementById('estadoPartido');
    var iconos = { gol: basketball ? '🏀' : '⚽', amarilla: '🟨', roja: '🟥', cambio: '🔄' };

    var idsVistos = {};
    var primeraCargaHecha = false;

    // Confeti hecho con <canvas>, sin librería externa (el CSP del sitio solo permite
    // scripts propios o de los CDN ya autorizados, y esto evita otra dependencia más).
    function lanzarConfeti() {
        var lienzo = document.createElement('canvas');
        lienzo.className = 'confeti-lienzo';
        lienzo.width = window.innerWidth;
        lienzo.height = window.innerHeight;
        document.body.appendChild(lienzo);
        var ctx = lienzo.getContext('2d');
        var colores = ['#7b2ff7', '#ff6b35', '#22d3ee', '#facc15', '#f472b6', '#4ade80'];
        var piezas = [];
        for (var i = 0; i < 160; i++) {
            piezas.push({
                x: Math.random() * lienzo.width,
                y: -20 - Math.random() * lienzo.height * 0.6,
                w: 6 + Math.random() * 6,
                h: 8 + Math.random() * 10,
                color: colores[Math.floor(Math.random() * colores.length)],
                vy: 2.5 + Math.random() * 3.5,
                vx: -2.5 + Math.random() * 5,
                rot: Math.random() * 360,
                vr: -10 + Math.random() * 20,
            });
        }
        var inicio = Date.now();
        function frame() {
            ctx.clearRect(0, 0, lienzo.width, lienzo.height);
            var algunaVisible = false;
            piezas.forEach(function (p) {
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vr;
                if (p.y < lienzo.height + 20) {
                    algunaVisible = true;
                }
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            if (algunaVisible && Date.now() - inicio < 4500) {
                requestAnimationFrame(frame);
            } else {
                lienzo.remove();
            }
        }
        requestAnimationFrame(frame);
    }

    function pulsarMarcador() {
        [marcadorLocalEl, marcadorVisitEl].forEach(function (el) {
            if (!el) {
                return;
            }
            el.classList.add('marcador-pulso');
            setTimeout(function () { el.classList.remove('marcador-pulso'); }, 900);
        });
    }

    function agregarFila(ev) {
        var vacio = feedEl.querySelector('.feed-evento-vacio');
        if (vacio) {
            vacio.remove();
        }
        var li = document.createElement('li');
        li.className = 'feed-evento feed-evento-nuevo';
        var minuto = ev.minuto !== null ? ev.minuto + "' " : '';
        li.innerHTML = '<span class="feed-icono">' + (iconos[ev.tipo] || '•') + '</span>' +
            '<span class="feed-texto">' + minuto + ev.descripcion + ' <span class="feed-equipo">— ' + ev.equipo + '</span></span>';
        feedEl.insertBefore(li, feedEl.firstChild);
        window.setTimeout(function () { li.classList.remove('feed-evento-nuevo'); }, 50);
    }

    function actualizar() {
        fetch(urlDatos, { cache: 'no-store' })
            .then(function (resp) { return resp.ok ? resp.json() : null; })
            .then(function (datos) {
                if (!datos || datos.error) {
                    return;
                }
                if (marcadorLocalEl) { marcadorLocalEl.textContent = datos.marcador_local; }
                if (marcadorVisitEl) { marcadorVisitEl.textContent = datos.marcador_visitante; }
                if (estadoEl) { estadoEl.textContent = datos.estado === 'jugado' ? 'Finalizado' : 'En vivo'; }

                var huboGolNuevo = false;
                datos.eventos.forEach(function (ev) {
                    if (idsVistos[ev.id]) {
                        return;
                    }
                    idsVistos[ev.id] = true;
                    agregarFila(ev);
                    if (primeraCargaHecha && ev.tipo === 'gol') {
                        huboGolNuevo = true;
                    }
                });
                primeraCargaHecha = true;

                if (huboGolNuevo) {
                    pulsarMarcador();
                    lanzarConfeti();
                }
            })
            .catch(function () { /* red intermitente: se reintenta en el próximo ciclo */ });
    }

    actualizar();
    setInterval(actualizar, 5000);

    var btnFull = document.getElementById('btnPantallaCompleta');
    if (btnFull) {
        btnFull.addEventListener('click', function () {
            var el = document.documentElement;
            if (!document.fullscreenElement) {
                var pedir = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
                if (pedir) { pedir.call(el); }
            } else {
                var salir = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
                if (salir) { salir.call(document); }
            }
        });
    }
})();
