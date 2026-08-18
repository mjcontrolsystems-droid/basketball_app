(function () {
    var contenedor = document.getElementById('partidoVivo');
    if (!contenedor) {
        return;
    }

    var urlDatos = contenedor.getAttribute('data-url-datos');
    var urlBalon = contenedor.getAttribute('data-url-balon');
    var marcadorLocalEl = document.getElementById('marcadorLocal');
    var marcadorVisitEl = document.getElementById('marcadorVisitante');
    var feedEl = document.getElementById('feedEventos');
    var estadoEl = document.getElementById('estadoPartido');
    var iconosPorTipo = {
        gol: '<img src="' + urlBalon + '" alt="" class="feed-balon">',
        amarilla: '<i class="bi bi-square-fill text-warning"></i>',
        roja: '<i class="bi bi-square-fill text-danger"></i>',
        cambio: '<i class="bi bi-arrow-left-right text-info"></i>',
    };

    // Reacción a pantalla completa por tipo de evento: partículas (mismo motor que el
    // confeti de un gol, con su propia paleta/cantidad/forma) + el banner grande de
    // assets/css/style.css (.banner-evento-<tipo>). "cambio" es deliberadamente más
    // discreto (menos partículas) que un gol o una tarjeta.
    var reaccionesPorTipo = {
        gol: { colores: ['#7b2ff7', '#ff6b35', '#22d3ee', '#facc15', '#f472b6', '#4ade80'], cantidad: 160, forma: 'rect' },
        amarilla: { colores: ['#facc15', '#fde047', '#eab308'], cantidad: 80, forma: 'rect' },
        roja: { colores: ['#f87171', '#ef4444', '#b91c1c'], cantidad: 80, forma: 'rect' },
        cambio: { colores: ['#38bdf8', '#0ea5e9', '#7dd3fc'], cantidad: 50, forma: 'circle' },
    };

    var bannerIconosPorTipo = {
        gol: '<img src="' + urlBalon + '" alt="" class="banner-evento-balon">',
        amarilla: '<i class="bi bi-square-fill"></i>',
        roja: '<i class="bi bi-square-fill"></i>',
        cambio: '<i class="bi bi-arrow-left-right"></i>',
    };

    var bannerTextosPorTipo = {
        gol: contenedor.getAttribute('data-texto-gol') || '¡GOL!',
        amarilla: contenedor.getAttribute('data-texto-amarilla') || 'TARJETA AMARILLA',
        roja: contenedor.getAttribute('data-texto-roja') || 'TARJETA ROJA',
        cambio: contenedor.getAttribute('data-texto-cambio') || 'CAMBIO',
    };

    var idsVistos = {};
    var primeraCargaHecha = false;

    // Cronómetro: arranca con lo que ya trae la página (evita esperar el primer sondeo
    // para mostrar algo) y luego se corrige con cada respuesta de actualizar(), por si el
    // organizador inicia/pausa/finaliza el cronómetro mientras alguien está viendo esta página.
    var cronometroEl = document.getElementById('cronometroVivo');
    var periodoEl = document.getElementById('periodoVivo');
    var extraEl = document.getElementById('extraVivo');
    var cronoEstado = contenedor.getAttribute('data-cronometro-estado') || 'detenido';
    var cronoSegundosBase = parseInt(contenedor.getAttribute('data-cronometro-segundos'), 10) || 0;
    var cronoInicioAttr = contenedor.getAttribute('data-cronometro-inicio');
    var cronoInicioMs = cronoInicioAttr ? new Date(cronoInicioAttr).getTime() : null;
    // Duración del periodo actual: los minutos configurados en la copa (15, 20, 45...) más
    // el tiempo extra agregado dentro del encuentro. Es desde donde baja la cuenta regresiva.
    var cronoDuracionSegundos = parseInt(contenedor.getAttribute('data-duracion-segundos'), 10) || 0;

    // Tiempo TRANSCURRIDO desde que arrancó el periodo actual (fuente de verdad guardada).
    function cronoSegundosActuales() {
        if (cronoEstado === 'corriendo' && cronoInicioMs !== null && !isNaN(cronoInicioMs)) {
            return cronoSegundosBase + Math.max(0, Math.floor((Date.now() - cronoInicioMs) / 1000));
        }
        return cronoSegundosBase;
    }

    function actualizarTextoCronometro() {
        if (!cronometroEl) {
            return;
        }
        // Cuenta REGRESIVA en los dos deportes: el reloj arranca en los minutos que
        // configuró el organizador y corre hacia 00:00, como un marcador de cancha.
        var restantes = Math.max(0, cronoDuracionSegundos - cronoSegundosActuales());
        var mm = Math.floor(restantes / 60);
        var ss = restantes % 60;
        cronometroEl.textContent = (mm < 10 ? '0' : '') + mm + ':' + (ss < 10 ? '0' : '') + ss;
        // Se acabó el tiempo del periodo y el reloj sigue corriendo: se resalta, que es la
        // señal de que toca cerrar el tiempo o agregar minutos extra.
        cronometroEl.classList.toggle('cronometro-agotado', restantes === 0 && cronoDuracionSegundos > 0);
    }

    actualizarTextoCronometro();
    setInterval(actualizarTextoCronometro, 1000);

    // Partículas hechas con <canvas>, sin librería externa (el CSP del sitio solo permite
    // scripts propios o de los CDN ya autorizados, y esto evita otra dependencia más).
    function lanzarParticulas(colores, cantidad, forma) {
        var lienzo = document.createElement('canvas');
        lienzo.className = 'confeti-lienzo';
        lienzo.width = window.innerWidth;
        lienzo.height = window.innerHeight;
        document.body.appendChild(lienzo);
        var ctx = lienzo.getContext('2d');
        var piezas = [];
        for (var i = 0; i < cantidad; i++) {
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
                if (forma === 'circle') {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.w / 2, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                }
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

    var bannerEl = document.getElementById('bannerEvento');
    var bannerIconoEl = document.getElementById('bannerEventoIcono');
    var bannerTextoEl = document.getElementById('bannerEventoTexto');

    function mostrarBanner(tipo) {
        if (!bannerEl) {
            return;
        }
        // Reinicia clases + contenido en cada reacción (por si un tipo distinto llega
        // mientras el banner anterior todavía se estaba ocultando).
        bannerEl.className = 'banner-evento banner-evento-' + tipo;
        if (bannerIconoEl) { bannerIconoEl.innerHTML = bannerIconosPorTipo[tipo] || ''; }
        if (bannerTextoEl) { bannerTextoEl.textContent = bannerTextosPorTipo[tipo] || ''; }
        void bannerEl.offsetWidth; // fuerza reflow para poder reiniciar la animación
        bannerEl.classList.add('banner-evento-activo');
        window.setTimeout(function () { bannerEl.classList.remove('banner-evento-activo'); }, 2400);
    }

    // Si llegan varios eventos nuevos en el mismo ciclo (el admin cargó varios seguido),
    // se disparan uno tras otro en vez de superponerse todos a la vez.
    var colaReacciones = [];
    var procesandoCola = false;

    function dispararReaccion(tipo) {
        var cfg = reaccionesPorTipo[tipo];
        if (!cfg) {
            return;
        }
        lanzarParticulas(cfg.colores, cfg.cantidad, cfg.forma);
        mostrarBanner(tipo);
        if (tipo === 'gol') {
            pulsarMarcador();
        }
    }

    function procesarCola() {
        if (procesandoCola || colaReacciones.length === 0) {
            return;
        }
        procesandoCola = true;
        dispararReaccion(colaReacciones.shift());
        window.setTimeout(function () {
            procesandoCola = false;
            procesarCola();
        }, 1000);
    }

    function encolarReaccion(tipo) {
        if (!reaccionesPorTipo[tipo]) {
            return;
        }
        colaReacciones.push(tipo);
        procesarCola();
    }

    function agregarFila(ev) {
        var vacio = feedEl.querySelector('.feed-evento-vacio');
        if (vacio) {
            vacio.remove();
        }
        var li = document.createElement('li');
        li.className = 'feed-evento feed-evento-nuevo feed-evento-' + ev.tipo;
        // ev.descripcion ya viene con el minuto incluido (evento_descripcion() en PHP),
        // no hay que anteponerlo de nuevo aquí o el minuto se ve repetido dos veces.
        li.innerHTML = '<span class="feed-icono">' + (iconosPorTipo[ev.tipo] || '•') + '</span>' +
            '<span class="feed-texto">' + ev.descripcion + ' <span class="feed-equipo">— ' + ev.equipo + '</span></span>';
        feedEl.insertBefore(li, feedEl.firstChild);
        window.setTimeout(function () { li.classList.remove('feed-evento-nuevo'); }, 50);
    }

    // Cartel de "Partido finalizado": aparece en cuanto el organizador cierra el encuentro
    // (resultado en firme o cronómetro finalizado), sin que nadie tenga que recargar. El
    // marcador ya está pintado arriba, así que aquí solo se anuncia quién ganó.
    var avisoFinalEl = document.getElementById('avisoFinal');
    var avisoFinalDetalleEl = document.getElementById('avisoFinalDetalle');
    var badgeEnVivoEl = document.getElementById('badgeEnVivo');
    var nombreLocal = contenedor.getAttribute('data-nombre-local') || 'Local';
    var nombreVisitante = contenedor.getAttribute('data-nombre-visitante') || 'Visitante';
    var yaFinalizado = contenedor.getAttribute('data-finalizado') === '1';

    function textoResultadoFinal(golesLocal, golesVisitante) {
        if (golesLocal > golesVisitante) {
            return 'Gana ' + nombreLocal + ' ' + golesLocal + '-' + golesVisitante;
        }
        if (golesVisitante > golesLocal) {
            return 'Gana ' + nombreVisitante + ' ' + golesVisitante + '-' + golesLocal;
        }
        return 'Empate ' + golesLocal + '-' + golesVisitante;
    }

    function marcarFinalizado(golesLocal, golesVisitante, conCelebracion) {
        if (avisoFinalDetalleEl) {
            avisoFinalDetalleEl.textContent = textoResultadoFinal(golesLocal, golesVisitante);
        }
        if (avisoFinalEl) {
            avisoFinalEl.classList.add('aviso-final-visible');
        }
        if (badgeEnVivoEl) {
            badgeEnVivoEl.classList.add('badge-finalizado');
        }
        if (estadoEl) {
            estadoEl.textContent = 'Finalizado';
        }
        if (conCelebracion) {
            lanzarParticulas(['#ffffff', '#facc15', '#4ade80', '#38bdf8'], 200, 'rect');
        }
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

                // 'finalizado' lo calcula el servidor (resultado en firme o cronómetro
                // finalizado); el fallback cubre una respuesta vieja en caché.
                var finalizado = datos.finalizado !== undefined ? datos.finalizado : (datos.estado === 'jugado');
                if (finalizado) {
                    // La celebración solo la primera vez: si alguien abre la página con el
                    // partido ya terminado, ve el cartel pero no el confeti de cierre.
                    marcarFinalizado(datos.marcador_local, datos.marcador_visitante, !yaFinalizado && primeraCargaHecha);
                    yaFinalizado = true;
                } else {
                    // El organizador reabrió el encuentro para corregirlo: vuelve a estar
                    // en juego, así que el cartel de FINAL tiene que desaparecer.
                    yaFinalizado = false;
                    if (estadoEl) { estadoEl.textContent = 'En vivo'; }
                    if (avisoFinalEl) { avisoFinalEl.classList.remove('aviso-final-visible'); }
                    if (badgeEnVivoEl) { badgeEnVivoEl.classList.remove('badge-finalizado'); }
                }

                if (datos.duracion_segundos) {
                    cronoDuracionSegundos = datos.duracion_segundos;
                }
                if (extraEl && datos.minutos_extra !== undefined) {
                    extraEl.textContent = '+' + datos.minutos_extra + ' min';
                    extraEl.classList.toggle('d-none', !datos.minutos_extra);
                }
                if (datos.cronometro_estado !== undefined) {
                    cronoEstado = datos.cronometro_estado;
                    cronoSegundosBase = datos.cronometro_segundos || 0;
                    cronoInicioMs = datos.cronometro_inicio ? new Date(datos.cronometro_inicio).getTime() : null;
                }
                actualizarTextoCronometro();
                if (periodoEl && datos.periodo_etiqueta) {
                    periodoEl.textContent = datos.periodo_etiqueta;
                }

                datos.eventos.forEach(function (ev) {
                    if (idsVistos[ev.id]) {
                        return;
                    }
                    idsVistos[ev.id] = true;
                    agregarFila(ev);
                    if (primeraCargaHecha) {
                        encolarReaccion(ev.tipo);
                    }
                });
                primeraCargaHecha = true;
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
