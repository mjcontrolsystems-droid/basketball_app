document.addEventListener('DOMContentLoaded', function () {
    // Navbar: fondo sólido al hacer scroll
    var nav = document.querySelector('.navbar-copa');
    if (nav) {
        var onScroll = function () {
            nav.classList.toggle('scrolled', window.scrollY > 24);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // Tabla de posiciones: toda la fila lleva al perfil del equipo (no solo el link del nombre)
    document.querySelectorAll('.fila-clicable[data-href]').forEach(function (fila) {
        fila.style.cursor = 'pointer';
        fila.addEventListener('click', function (e) {
            if (e.target.closest('a, button')) {
                return;
            }
            window.location.href = fila.getAttribute('data-href');
        });
    });

    // Confirmación para acciones destructivas en el panel del organizador. Si el usuario
    // cancela, el formulario se resetea: importante para el switch de "Jugado", que si no
    // quedaría visualmente cambiado aunque la acción nunca se envió.
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
                form.reset();
            }
        });
    });

    // Ficha de partido: "Descargar PDF" abre el diálogo de impresión del navegador
    // (con la hoja de estilo @media print ya aplicada); ahí el usuario elige
    // "Guardar como PDF". No genera el PDF en el servidor.
    document.querySelectorAll('.btn-imprimir-pdf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });

    // Ficha de eventos del partido (admin/partido_eventos.php): cada select de
    // jugador solo debe mostrar la plantilla del equipo elegido en el mismo
    // formulario, para no mezclar jugadores de ambos equipos al cargar un evento.
    //
    // Las opciones que no corresponden se QUITAN del DOM en vez de marcarlas con
    // option.hidden: Safari (iPhone/iPad) ignora tanto `hidden` como `display:none`
    // en un <option>, así que con el método anterior el organizador seguía viendo
    // -y podía elegir- jugadores del equipo contrario desde el teléfono. Se guarda
    // la lista completa aparte para poder restaurarla al cambiar de equipo.
    document.querySelectorAll('form').forEach(function (form) {
        var equipoSelect = form.querySelector('select[name="equipo_id"]');
        var jugadorSelects = form.querySelectorAll('select[data-filtra-jugador]');
        if (!equipoSelect || jugadorSelects.length === 0) {
            return;
        }

        var opcionesPorSelect = [];
        Array.prototype.forEach.call(jugadorSelects, function (select) {
            opcionesPorSelect.push(Array.prototype.slice.call(select.options));
        });

        var actualizarJugadores = function () {
            var equipoId = equipoSelect.value;
            Array.prototype.forEach.call(jugadorSelects, function (select, i) {
                var todas = opcionesPorSelect[i];
                while (select.firstChild) {
                    select.removeChild(select.firstChild);
                }
                todas.forEach(function (opcion) {
                    // El placeholder ("Jugador que anota...", "Sin asistencia") no
                    // pertenece a ningún equipo y siempre debe seguir disponible.
                    if (opcion.value === '' || opcion.getAttribute('data-equipo') === equipoId) {
                        select.appendChild(opcion);
                    }
                });
                select.value = '';
            });
        };

        equipoSelect.addEventListener('change', actualizarJugadores);
        actualizarJugadores();
    });

    // Cronómetro del partido (admin/partido_eventos.php): tic visual en vivo y
    // autocompletar el campo "Min." de los 3 formularios de eventos con el minuto
    // actual, mientras el organizador no lo haya editado a mano (así se sigue la
    // cronología real del partido al cargar goles/tarjetas/cambios).
    var cronometro = document.getElementById('cronometroPartido');
    if (cronometro) {
        var cronoEstado = cronometro.getAttribute('data-estado');
        var cronoSegundosBase = parseInt(cronometro.getAttribute('data-segundos'), 10) || 0;
        var cronoInicioIso = cronometro.getAttribute('data-inicio');
        var cronoInicioMs = cronoInicioIso ? new Date(cronoInicioIso).getTime() : null;
        var cronoBasketball = cronometro.getAttribute('data-basketball') === '1';
        var cronoDuracionSegundos = parseInt(cronometro.getAttribute('data-duracion-segundos'), 10) || 0;
        var cronoTextoEl = document.getElementById('cronometroTexto');
        var camposMinuto = document.querySelectorAll('input[name="minuto"]');
        var camposMinutoTocados = {};

        camposMinuto.forEach(function (campo, i) {
            campo.addEventListener('input', function () {
                // Editar a mano pausa el autocompletado de ESTE campo (para poder anotar
                // un gol que se pasó, con su minuto real)... pero no para siempre:
                // BORRAR el número (dejarlo vacío) lo vuelve a enganchar al cronómetro.
                camposMinutoTocados[i] = campo.value !== '';
            });
            // Al enviar el formulario del evento, el campo suelta el valor manual y
            // vuelve a seguir al cronómetro para el siguiente evento — antes quedaba
            // estancado en el número escrito hasta recargar la página.
            if (campo.form) {
                campo.form.addEventListener('submit', function () {
                    camposMinutoTocados[i] = false;
                });
            }
        });

        // Siempre tiempo TRANSCURRIDO (cuenta hacia adelante desde 00:00), sin importar el
        // deporte: es la fuente de verdad para el minuto sugerido y para lo que se guarda.
        var cronoSegundosActuales = function () {
            if (cronoEstado === 'corriendo' && cronoInicioMs !== null && !isNaN(cronoInicioMs)) {
                return cronoSegundosBase + Math.max(0, Math.floor((Date.now() - cronoInicioMs) / 1000));
            }
            return cronoSegundosBase;
        };

        var actualizarCronometro = function () {
            var segundosTranscurridos = cronoSegundosActuales();

            // En basketball el reloj se MUESTRA en cuenta regresiva (de cronoDuracionSegundos
            // a 0); en fútbol se muestra tal cual, contando hacia adelante.
            var segundosMostrados = cronoBasketball
                ? Math.max(0, cronoDuracionSegundos - segundosTranscurridos)
                : segundosTranscurridos;
            if (cronoTextoEl) {
                var mm = Math.floor(segundosMostrados / 60);
                var ss = segundosMostrados % 60;
                cronoTextoEl.textContent = (mm < 10 ? '0' + mm : '' + mm) + ':' + (ss < 10 ? '0' + ss : '' + ss);
            }

            // El minuto sugerido para los eventos SIEMPRE se calcula sobre lo transcurrido
            // (no sobre la cuenta regresiva mostrada): se aproxima al minuto siguiente
            // pasando los 30 segundos (ej. 4:35 -> 5), y nunca baja de 1.
            var minutosTranscurridos = Math.floor(segundosTranscurridos / 60);
            var segundosRestoTranscurridos = segundosTranscurridos % 60;
            var minutoSugerido = segundosRestoTranscurridos >= 30 ? minutosTranscurridos + 1 : minutosTranscurridos;
            if (minutoSugerido < 1) {
                minutoSugerido = 1;
            }
            camposMinuto.forEach(function (campo, i) {
                if (!camposMinutoTocados[i] && document.activeElement !== campo) {
                    campo.value = minutoSugerido;
                }
            });
        };

        actualizarCronometro();
        if (cronoEstado === 'corriendo') {
            setInterval(actualizarCronometro, 1000);
        }
    }

    // Formulario de encuentros: el campo "Jornada" solo aplica a la fase de grupos
    var selectFase = document.getElementById('selectFase');
    var grupoJornada = document.getElementById('grupoJornada');
    if (selectFase && grupoJornada) {
        var actualizarVisibilidadJornada = function () {
            grupoJornada.style.display = selectFase.value === 'grupos' ? '' : 'none';
        };
        actualizarVisibilidadJornada();
        selectFase.addEventListener('change', actualizarVisibilidadJornada);
    }

    // Formulario de copas: sugiere la URL (slug) a partir del nombre, mientras el usuario no la edite a mano
    var campoNombre = document.getElementById('campoNombre');
    var campoSlug = document.getElementById('campoSlug');
    var previewUrlCopa = document.getElementById('previewUrlCopa');
    if (campoSlug && previewUrlCopa) {
        var actualizarPreviewUrl = function () {
            var esPredeterminado = campoSlug.getAttribute('data-predeterminado') === '1';
            var origen = campoSlug.getAttribute('data-origen') || '';
            previewUrlCopa.textContent = esPredeterminado ? (origen + '/') : (origen + '/' + campoSlug.value + '/');
        };
        campoSlug.addEventListener('input', actualizarPreviewUrl);
    }
    if (campoNombre && campoSlug) {
        var slugTocadoAMano = campoSlug.value.trim() !== '';
        campoSlug.addEventListener('input', function () { slugTocadoAMano = true; });
        campoNombre.addEventListener('input', function () {
            if (slugTocadoAMano) {
                return;
            }
            var mapa = { 'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ñ': 'n', 'ü': 'u' };
            var texto = campoNombre.value.toLowerCase().replace(/[áéíóúñü]/g, function (c) { return mapa[c]; });
            campoSlug.value = texto.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (previewUrlCopa) {
                actualizarPreviewUrl();
            }
        });
    }

    // Formulario de copas: el select de Modalidad solo muestra las del deporte elegido
    // (fútbol 11/7/5 o basketball FIBA/NBA) y sugiere la duración reglamentaria del
    // tiempo/cuarto como placeholder. Mismo patrón de quitar/restaurar opciones que el
    // filtro de jugadores (option.hidden no funciona en Safari/iPhone).
    var selectModalidad = document.getElementById('selectModalidad');
    var campoDuracion = document.getElementById('campoDuracionPeriodo');
    var selectDeporteRef = document.getElementById('selectDeporte');
    if (selectModalidad && selectDeporteRef) {
        var todasModalidades = Array.prototype.slice.call(selectModalidad.options);
        var filtrarModalidades = function (conservarSeleccion) {
            var dep = selectDeporteRef.value;
            var seleccionPrevia = selectModalidad.value;
            while (selectModalidad.firstChild) {
                selectModalidad.removeChild(selectModalidad.firstChild);
            }
            todasModalidades.forEach(function (op) {
                if (op.getAttribute('data-deporte') === dep) {
                    selectModalidad.appendChild(op);
                }
            });
            if (conservarSeleccion && seleccionPrevia) {
                selectModalidad.value = seleccionPrevia;
            }
            if (!selectModalidad.value && selectModalidad.options.length > 0) {
                selectModalidad.selectedIndex = 0;
            }
            sugerirDuracion();
        };
        var sugerirDuracion = function () {
            if (!campoDuracion) { return; }
            var op = selectModalidad.options[selectModalidad.selectedIndex];
            if (op) {
                campoDuracion.placeholder = op.getAttribute('data-duracion') + ' min (reglamentario)';
            }
        };
        selectDeporteRef.addEventListener('change', function () { filtrarModalidades(false); });
        selectModalidad.addEventListener('change', sugerirDuracion);
        filtrarModalidades(true);
    }

    // Formulario de copas: al elegir el deporte, sugiere si hay empates y cuántos puntos vale cada resultado
    var selectDeporte = document.getElementById('selectDeporte');
    if (selectDeporte) {
        var checkEmpates = document.getElementById('checkEmpates');
        var campoPtsVictoria = document.getElementById('campoPtsVictoria');
        var campoPtsEmpate = document.getElementById('campoPtsEmpate');
        var campoPtsDerrota = document.getElementById('campoPtsDerrota');
        var presets = {
            basketball: { empates: false, victoria: 2, empate: 0, derrota: 1 },
            futbol: { empates: true, victoria: 3, empate: 1, derrota: 0 },
        };
        selectDeporte.addEventListener('change', function () {
            var preset = presets[selectDeporte.value];
            if (!preset) { return; }
            checkEmpates.checked = preset.empates;
            campoPtsVictoria.value = preset.victoria;
            campoPtsEmpate.value = preset.empate;
            campoPtsDerrota.value = preset.derrota;
        });
    }

    // Controles que envían su formulario al cambiar (ej. el switch "Jugado" de cada
    // encuentro). Va aquí y no en un onchange inline porque el CSP del sitio bloquea
    // todo JavaScript dentro del HTML — el switch se veía pero no hacía nada.
    // requestSubmit (y no submit) para que dispare el evento 'submit' y el data-confirm
    // de reapertura de resultados pueda interceptarlo.
    document.querySelectorAll('[data-envia-al-cambiar]').forEach(function (control) {
        control.addEventListener('change', function () {
            if (!control.form) {
                return;
            }
            if (control.form.requestSubmit) {
                control.form.requestSubmit();
            } else {
                control.form.submit();
            }
        });
    });

    // Ficha del partido con ?imprimir=1: abre el diálogo de impresión apenas carga
    // (mismo motivo CSP: el <script> inline que hacía esto nunca llegaba a ejecutarse).
    if (document.querySelector('[data-imprimir-al-cargar]')) {
        window.addEventListener('load', function () {
            window.print();
        });
    }

    // Modales que deben abrirse solos al cargar la página (ej. aviso de fecha futura en
    // la ficha de eventos del partido). Se hace aquí, en el JS externo, porque el CSP del
    // sitio no permite <script> inline en las páginas.
    document.querySelectorAll('[data-modal-auto]').forEach(function (el) {
        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });

    // Auto-cierre de alertas flash
    document.querySelectorAll('.alert[data-autoclose]').forEach(function (alerta) {
        setTimeout(function () {
            var instancia = bootstrap.Alert.getOrCreateInstance(alerta);
            instancia.close();
        }, 4500);
    });

    // Modal de compartir: genera el QR de la página actual y permite copiar el enlace
    var modalCompartir = document.getElementById('modalCompartir');
    if (modalCompartir) {
        modalCompartir.addEventListener('show.bs.modal', function () {
            var url = window.location.href;
            var input = document.getElementById('inputEnlaceCompartir');
            if (input) {
                input.value = url;
            }
            var contenedorQr = document.getElementById('qrCompartir');
            if (contenedorQr) {
                contenedorQr.innerHTML = '';
                if (window.QRCode) {
                    new QRCode(contenedorQr, {
                        text: url,
                        width: 180,
                        height: 180,
                        colorDark: '#241a3a',
                        colorLight: '#ffffff',
                    });
                }
            }
        });

        var btnCopiar = document.getElementById('btnCopiarEnlace');
        if (btnCopiar) {
            btnCopiar.addEventListener('click', function () {
                var input = document.getElementById('inputEnlaceCompartir');
                var textoOriginal = btnCopiar.innerHTML;
                var marcarCopiado = function () {
                    btnCopiar.innerHTML = '<i class="bi bi-check-lg me-1"></i>¡Copiado!';
                    setTimeout(function () { btnCopiar.innerHTML = textoOriginal; }, 2000);
                };
                var copiarConExecCommand = function () {
                    input.removeAttribute('readonly');
                    input.select();
                    input.setSelectionRange(0, input.value.length);
                    try {
                        document.execCommand('copy');
                    } catch (e) { /* noop: último recurso, ya se seleccionó el texto para copiar manualmente */ }
                    input.setAttribute('readonly', true);
                    marcarCopiado();
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(marcarCopiado, copiarConExecCommand);
                } else {
                    copiarConExecCommand();
                }
            });
        }
    }

    // Tarjetas de "Mis Copas": copiar la URL de una copa específica
    document.querySelectorAll('.btn-copiar-url').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var url = boton.getAttribute('data-url');
            var iconoOriginal = boton.innerHTML;
            var marcarCopiado = function () {
                boton.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                setTimeout(function () { boton.innerHTML = iconoOriginal; }, 1800);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(marcarCopiado);
            } else {
                var temporal = document.createElement('input');
                temporal.value = url;
                document.body.appendChild(temporal);
                temporal.select();
                try { document.execCommand('copy'); } catch (e) { /* noop */ }
                document.body.removeChild(temporal);
                marcarCopiado();
            }
        });
    });
});
