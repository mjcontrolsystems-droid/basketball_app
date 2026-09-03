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
    // Muestra u oculta un bloque del formulario, deshabilitando sus campos al ocultarlo.
    //
    // Lo de deshabilitar no es cosmético, arregla un bug feo: un campo escondido que no
    // pasa la validación del navegador (min, max, required) hace que el envío se cancele
    // y que el navegador intente enfocarlo para señalar el error. Como no se ve, no
    // aparece ningún mensaje y el botón de guardar parece estar roto. Pasó de verdad con
    // "número de grupos" (min 2) valiendo 0 en las copas que no usan grupos.
    //
    // Un campo deshabilitado queda fuera de la validación y tampoco se envía, que es
    // justo lo que se quiere: si el bloque no aplica, sus datos no deberían viajar.
    var mostrarBloque = function (bloque, visible) {
        if (!bloque) {
            return;
        }
        bloque.style.display = visible ? '' : 'none';
        bloque.querySelectorAll('input, select, textarea').forEach(function (campo) {
            campo.disabled = !visible;
        });
    };

    // Colores de la marca, para que SweetAlert2 no desentone con el resto del sitio.
    var COLOR_ACCION = '#7b2ff7';
    var COLOR_PELIGRO = '#e24b4a';

    // Si SweetAlert2 no cargó (CDN caído, sin conexión), se cae a los diálogos del
    // navegador en vez de dejar botones que no hacen nada.
    var haySwal = function () {
        return typeof window.Swal !== 'undefined';
    };

    var avisar = function (tipo, mensaje) {
        if (!haySwal()) {
            window.alert(mensaje);
            return;
        }
        // Un error se queda hasta que lo cierras; un "guardado correctamente" no debe
        // estorbar, así que sale como aviso chico en la esquina y se va solo.
        if (tipo === 'error') {
            window.Swal.fire({
                icon: 'error',
                title: 'Algo no salió bien',
                text: mensaje,
                confirmButtonColor: COLOR_ACCION,
                confirmButtonText: 'Entendido'
            });
            return;
        }
        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: tipo === 'warning' ? 'warning' : 'success',
            title: mensaje,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    };
    window.avisoApp = avisar;

    // Mensaje que dejó la página anterior (guardado, error de validación, etc.). Viaja en
    // data-attributes porque el CSP no permite JavaScript inline.
    var flash = document.getElementById('datosFlash');
    if (flash && flash.dataset.mensaje) {
        avisar(flash.dataset.tipo, flash.dataset.mensaje);
    }

    // Formularios que piden confirmación antes de enviarse (eliminar, rehacer calendario,
    // sortear grupos...). Con SweetAlert2 la respuesta es asíncrona, así que se corta el
    // envío siempre y se reenvía a mano si el usuario confirma.
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        var yaConfirmado = false;
        form.addEventListener('submit', function (e) {
            if (yaConfirmado) {
                return; // segundo paso: dejar pasar el envío de verdad
            }
            e.preventDefault();

            var enviar = function () {
                yaConfirmado = true;
                // El botón que disparó el envío puede llevar name/value que el servidor
                // necesita (por ejemplo "ver vista previa" contra "crear"). Al reenviar
                // por código ese dato se perdería, así que se agrega como campo oculto.
                var boton = e.submitter;
                if (boton && boton.name) {
                    var oculto = document.createElement('input');
                    oculto.type = 'hidden';
                    oculto.name = boton.name;
                    oculto.value = boton.value;
                    form.appendChild(oculto);
                }
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            };

            if (!haySwal()) {
                if (window.confirm(form.getAttribute('data-confirm'))) { enviar(); }
                return;
            }

            // Rojo solo cuando de verdad se borra algo, para que el color signifique algo.
            var esBorrado = /elimin|borrar|se pierde|no se puede deshacer/i.test(form.getAttribute('data-confirm'));
            window.Swal.fire({
                title: '¿Confirmas?',
                text: form.getAttribute('data-confirm'),
                icon: esBorrado ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: esBorrado ? 'Sí, continuar' : 'Continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: esBorrado ? COLOR_PELIGRO : COLOR_ACCION,
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: esBorrado
            }).then(function (r) {
                if (r.isConfirmed) { enviar(); }
            });
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
                    // El placeholder ("Jugador que anota...", "Sin asistencia") y la
                    // opción "Sin identificar" (valor 0) no pertenecen a ningún equipo y
                    // siempre deben seguir disponibles. Sin esto, el filtro borraba la de
                    // "Sin identificar" al elegir equipo y no había forma de usarla.
                    if (opcion.value === '' || opcion.value === '0' || opcion.getAttribute('data-equipo') === equipoId) {
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
        // Duración del periodo en curso (minutos configurados en la copa + tiempo extra):
        // desde aquí baja la cuenta regresiva.
        var cronoDuracionSegundos = parseInt(cronometro.getAttribute('data-duracion-segundos'), 10) || 0;
        // Minuto en el que arranca este periodo (45' el 2do tiempo de fútbol 11, 20' el 3er
        // cuarto FIBA...), para que el minuto sugerido siga la numeración real del partido.
        var cronoMinutoBase = parseInt(cronometro.getAttribute('data-minuto-base'), 10) || 0;
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

            // El reloj se MUESTRA en cuenta regresiva en los dos deportes: arranca en los
            // minutos configurados para la copa y corre hacia 00:00.
            var segundosMostrados = Math.max(0, cronoDuracionSegundos - segundosTranscurridos);
            if (cronoTextoEl) {
                var mm = Math.floor(segundosMostrados / 60);
                var ss = segundosMostrados % 60;
                cronoTextoEl.textContent = (mm < 10 ? '0' + mm : '' + mm) + ':' + (ss < 10 ? '0' + ss : '' + ss);
                // Tiempo agotado con el periodo todavía abierto: toca agregar minutos
                // extra o pasar al siguiente periodo.
                cronoTextoEl.classList.toggle('cronometro-agotado', segundosMostrados === 0 && cronoDuracionSegundos > 0);
            }

            // El minuto sugerido para los eventos SIEMPRE se calcula sobre lo transcurrido
            // (no sobre la cuenta regresiva mostrada), y arranca desde el minuto base del
            // periodo para no reiniciar la numeración en cada tiempo/cuarto. Se aproxima al
            // minuto siguiente pasando los 30 segundos (ej. 4:35 -> 5), y nunca baja de 1.
            var minutosTranscurridos = Math.floor(segundosTranscurridos / 60);
            var segundosRestoTranscurridos = segundosTranscurridos % 60;
            var minutoSugerido = cronoMinutoBase + (segundosRestoTranscurridos >= 30 ? minutosTranscurridos + 1 : minutosTranscurridos);
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

    // Alineación del encuentro (admin/partido_eventos.php): contador vivo de titulares por
    // equipo. El tope lo marca la modalidad de la copa (5, 7 u 11 jugadores en cancha) y
    // también se valida en el servidor al guardar; esto es para que el organizador vea al
    // instante cuántos lleva marcados y no descubra el error hasta enviar el formulario.
    document.querySelectorAll('form[data-alineacion]').forEach(function (form) {
        var maximo = parseInt(form.getAttribute('data-max-titulares'), 10) || 0;
        var aviso = form.querySelector('[data-aviso-alineacion]');
        var botonGuardar = form.querySelector('button[type="submit"]');

        var recontar = function () {
            var algunExceso = false;
            form.querySelectorAll('[data-equipo-alineacion]').forEach(function (bloque) {
                var marcados = bloque.querySelectorAll('.check-titular:checked').length;
                var contador = bloque.querySelector('[data-contador-titulares]');
                var cuenta = bloque.querySelector('[data-cuenta]');
                if (cuenta) {
                    cuenta.textContent = marcados;
                }
                if (contador) {
                    // Verde al llegar justo al número de la modalidad, rojo si se pasó.
                    contador.classList.toggle('text-bg-success', marcados === maximo);
                    contador.classList.toggle('text-bg-danger', marcados > maximo);
                    contador.classList.toggle('text-bg-light', marcados < maximo);
                }
                if (marcados > maximo) {
                    algunExceso = true;
                }
            });
            if (aviso) {
                aviso.textContent = algunExceso ? 'Hay más de ' + maximo + ' titulares marcados en un equipo. Pasa a la banca los que sobran.' : '';
                aviso.classList.toggle('d-none', !algunExceso);
            }
            if (botonGuardar) {
                botonGuardar.disabled = algunExceso;
            }
        };

        form.querySelectorAll('.check-titular').forEach(function (check) {
            check.addEventListener('change', recontar);
        });
        recontar();
    });

    // Formulario de encuentros: el campo "Jornada" solo aplica a la fase de grupos
    var selectFase = document.getElementById('selectFase');
    var grupoJornada = document.getElementById('grupoJornada');
    if (selectFase && grupoJornada) {
        var actualizarVisibilidadJornada = function () {
            // Mismo motivo que en el formulario de copas: el campo de jornada tiene un
            // tope (max), y oculto con un valor fuera de rango bloquearía el guardado.
            mostrarBloque(grupoJornada, selectFase.value === 'grupos');
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

    // Botón "X" sobre la miniatura de una imagen ya subida. Reemplaza a la casilla de
    // "quitar la actual": ver la imagen con su X encima es más directo que leer una
    // casilla, y deja claro CUÁL se está quitando cuando hay más de una.
    //
    // No borra nada al instante: marca el campo oculto y tacha la miniatura, y el borrado
    // ocurre al guardar. Así se puede deshacer y no se pierde nada por un clic de más.
    document.querySelectorAll('.btn-quitar-archivo').forEach(function (boton) {
        var figura = boton.closest('.vista-previa-item');
        var campo = document.getElementById(boton.getAttribute('data-campo'));
        if (!figura || !campo) {
            return;
        }

        var deshacer = figura.parentNode.querySelector('.deshacer-quitar');

        var marcar = function (quitar) {
            campo.value = quitar ? '1' : '0';
            figura.classList.toggle('archivo-quitado', quitar);
            boton.setAttribute('aria-pressed', quitar ? 'true' : 'false');
            if (deshacer) {
                deshacer.classList.toggle('d-none', !quitar);
            }
        };

        boton.addEventListener('click', function () {
            var nombre = boton.getAttribute('data-nombre') || 'la imagen';
            if (!haySwal()) {
                if (window.confirm('¿Quitar ' + nombre + '? Se aplicará al guardar.')) { marcar(true); }
                return;
            }
            window.Swal.fire({
                title: '¿Quitar ' + nombre + '?',
                text: boton.getAttribute('data-nota') || 'Se aplicará cuando guardes el formulario.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, quitar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: COLOR_PELIGRO,
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: true
            }).then(function (r) {
                if (r.isConfirmed) { marcar(true); }
            });
        });

        if (deshacer) {
            deshacer.addEventListener('click', function (e) {
                e.preventDefault();
                marcar(false);
            });
        }
    });

    // Calendario para marcar las fechas que no se juegan (feriados, fines de semana sin
    // confirmar). Antes había que escribirlas a mano en formato 2026-10-31, que es fácil
    // de equivocar y difícil de revisar.
    //
    // Solo se pueden marcar los días que la copa realmente juega: excluir un martes en una
    // liga de sábado y domingo no haría nada, y ofrecerlo solo confunde. Por eso el
    // calendario se redibuja cuando cambian los días de juego o la fecha de arranque.
    (function () {
        var contenedor = document.getElementById('calendarioExcluidas');
        if (!contenedor) {
            return;
        }
        var campo = document.getElementById(contenedor.getAttribute('data-campo'));
        var lista = document.getElementById('listaExcluidas');
        if (!campo) {
            return;
        }

        var NOMBRES_MES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
            'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        // El calendario se dibuja de lunes a domingo, como se lee en Guatemala.
        var CABECERAS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

        var aTexto = function (fecha) {
            var m = String(fecha.getMonth() + 1).padStart(2, '0');
            var d = String(fecha.getDate()).padStart(2, '0');
            return fecha.getFullYear() + '-' + m + '-' + d;
        };

        // Las fechas se manejan en horario local y no con new Date('2026-10-31'), que
        // ISO interpreta como UTC y en Guatemala devolvería el día anterior.
        var deTexto = function (texto) {
            var p = String(texto).trim().split('-');
            if (p.length !== 3) { return null; }
            var f = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
            return isNaN(f.getTime()) ? null : f;
        };

        var excluidas = new Set(
            campo.value.split(/[\s,;]+/).map(function (t) { return t.trim(); }).filter(Boolean)
        );

        var diasDeJuego = function () {
            var dias = [];
            document.querySelectorAll('input[name="' + contenedor.getAttribute('data-dias') + '"]:checked')
                .forEach(function (c) { dias.push(Number(c.value)); });
            return dias;
        };

        var mesVisible = null;

        var sincronizar = function () {
            var ordenadas = Array.from(excluidas).sort();
            campo.value = ordenadas.join(', ');
            if (!lista) { return; }
            lista.innerHTML = '';
            ordenadas.forEach(function (texto) {
                var f = deTexto(texto);
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'badge rounded-pill text-bg-danger border-0';
                chip.title = 'Quitar de la lista';
                chip.textContent = (f ? f.getDate() + ' ' + NOMBRES_MES[f.getMonth()] : texto) + ' ✕';
                chip.addEventListener('click', function () {
                    excluidas.delete(texto);
                    sincronizar();
                    dibujar();
                });
                lista.appendChild(chip);
            });
        };

        var dibujar = function () {
            var dias = diasDeJuego();
            contenedor.innerHTML = '';

            if (dias.length === 0) {
                var aviso = document.createElement('p');
                aviso.className = 'form-text mb-0';
                aviso.textContent = 'Marca primero los días de juego y aquí podrás elegir las fechas que no se jugarán.';
                contenedor.appendChild(aviso);
                return;
            }

            if (mesVisible === null) {
                var inicio = deTexto((document.getElementsByName(contenedor.getAttribute('data-inicio'))[0] || {}).value || '');
                var base = inicio || new Date();
                mesVisible = new Date(base.getFullYear(), base.getMonth(), 1);
            }

            var cabecera = document.createElement('div');
            cabecera.className = 'calendario-excluir__barra';
            var anterior = document.createElement('button');
            anterior.type = 'button';
            anterior.className = 'btn btn-sm btn-outline-secondary';
            anterior.innerHTML = '<i class="bi bi-chevron-left"></i>';
            anterior.setAttribute('aria-label', 'Mes anterior');
            anterior.addEventListener('click', function () {
                mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() - 1, 1);
                dibujar();
            });
            var titulo = document.createElement('span');
            titulo.className = 'fw-semibold small';
            titulo.textContent = NOMBRES_MES[mesVisible.getMonth()] + ' ' + mesVisible.getFullYear();
            var siguiente = document.createElement('button');
            siguiente.type = 'button';
            siguiente.className = 'btn btn-sm btn-outline-secondary';
            siguiente.innerHTML = '<i class="bi bi-chevron-right"></i>';
            siguiente.setAttribute('aria-label', 'Mes siguiente');
            siguiente.addEventListener('click', function () {
                mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + 1, 1);
                dibujar();
            });
            cabecera.appendChild(anterior);
            cabecera.appendChild(titulo);
            cabecera.appendChild(siguiente);
            contenedor.appendChild(cabecera);

            var grilla = document.createElement('div');
            grilla.className = 'calendario-excluir__grilla';
            CABECERAS.forEach(function (n) {
                var c = document.createElement('span');
                c.className = 'calendario-excluir__cabecera';
                c.textContent = n;
                grilla.appendChild(c);
            });

            var primero = new Date(mesVisible.getFullYear(), mesVisible.getMonth(), 1);
            // getDay() da 0 para domingo; se convierte a lunes = 0.
            var hueco = (primero.getDay() + 6) % 7;
            for (var i = 0; i < hueco; i++) {
                grilla.appendChild(document.createElement('span'));
            }

            var ultimo = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + 1, 0).getDate();
            for (var d = 1; d <= ultimo; d++) {
                var fecha = new Date(mesVisible.getFullYear(), mesVisible.getMonth(), d);
                var texto = aTexto(fecha);
                var seJuega = dias.indexOf(fecha.getDay()) !== -1;

                var celda = document.createElement('button');
                celda.type = 'button';
                celda.className = 'calendario-excluir__dia';
                celda.textContent = String(d);
                celda.dataset.fecha = texto;

                if (!seJuega) {
                    celda.classList.add('no-juega');
                    celda.disabled = true;
                    celda.title = 'Ese día no se juega';
                } else {
                    var marcada = excluidas.has(texto);
                    celda.classList.toggle('excluida', marcada);
                    celda.setAttribute('aria-pressed', marcada ? 'true' : 'false');
                    celda.title = marcada ? 'Volver a jugar este día' : 'Marcar como día sin juego';
                    celda.addEventListener('click', function () {
                        var f = this.dataset.fecha;
                        if (excluidas.has(f)) { excluidas.delete(f); } else { excluidas.add(f); }
                        sincronizar();
                        dibujar();
                    });
                }
                grilla.appendChild(celda);
            }

            contenedor.appendChild(grilla);
        };

        // Al cambiar los días de juego cambia qué se puede excluir; al cambiar la fecha de
        // arranque cambia el mes que conviene mostrar primero.
        document.querySelectorAll('input[name="' + contenedor.getAttribute('data-dias') + '"]').forEach(function (c) {
            c.addEventListener('change', dibujar);
        });
        var campoInicio = document.getElementsByName(contenedor.getAttribute('data-inicio'))[0];
        if (campoInicio) {
            campoInicio.addEventListener('change', function () {
                mesVisible = null;
                dibujar();
            });
        }

        sincronizar();
        dibujar();
    })();

    // Casillas que desbloquean otro campo (ej. "Ajustar la jornada manualmente"). El campo
    // llega readonly desde el servidor para que el valor automático se vea pero no se toque;
    // al marcar la casilla se libera. Aquí y no inline: el CSP bloquea el JavaScript en HTML.
    document.querySelectorAll('input[type="checkbox"][data-activa]').forEach(function (casilla) {
        var destino = document.querySelector(casilla.getAttribute('data-activa'));
        if (!destino) {
            return;
        }
        var aplicar = function () {
            destino.readOnly = !casilla.checked;
            if (casilla.checked) {
                destino.focus();
                destino.select();
            }
        };
        casilla.addEventListener('change', aplicar);
    });

    // Ficha del partido con ?imprimir=1: abre el diálogo de impresión apenas carga
    // (mismo motivo CSP: el <script> inline que hacía esto nunca llegaba a ejecutarse).
    if (document.querySelector('[data-imprimir-al-cargar]')) {
        window.addEventListener('load', function () {
            window.print();
        });
    }

    // Calendario imprimible: el selector de Jornada solo aplica al alcance "una jornada",
    // y el de Fase solo a "una fase" — se muestran/ocultan según lo elegido.
    var selectAlcance = document.getElementById('selectAlcance');
    if (selectAlcance) {
        var grupoJornadaImp = document.getElementById('grupoJornadaImprimir');
        var grupoFaseImp = document.getElementById('grupoFaseImprimir');
        var actualizarAlcance = function () {
            mostrarBloque(grupoJornadaImp, selectAlcance.value === 'jornada');
            mostrarBloque(grupoFaseImp, selectAlcance.value === 'fase');
        };
        selectAlcance.addEventListener('change', actualizarAlcance);
        actualizarAlcance();
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
            // El enlace lo decide la página (data-url-compartir): la vista principal de la
            // copa, o la transmisión en vivo cuando se comparte desde ahí. Siempre absoluto.
            // Solo si faltara ese dato se cae a la URL de la página actual.
            var url = modalCompartir.getAttribute('data-url-compartir') || window.location.href;
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

    // Vista previa de las imágenes que se suben (logo de la copa, escudo del equipo,
    // logo del patrocinador, foto de perfil): en cuanto el organizador elige el archivo
    // se ve cómo va a quedar, sin tener que guardar primero y descubrirlo después.
    // El archivo se lee LOCALMENTE con FileReader (data: URL) — no se sube nada hasta
    // enviar el formulario; el CSP del sitio permite img-src data: justamente para esto.
    document.querySelectorAll('input[type="file"][data-vista-previa]').forEach(function (input) {
        var contenedor = document.getElementById(input.getAttribute('data-vista-previa'));
        if (!contenedor) {
            return;
        }
        input.addEventListener('change', function () {
            // Quita la previa anterior (si el usuario cambió de archivo dos veces seguidas)
            var previaAnterior = contenedor.querySelector('.vista-previa-item-nueva');
            if (previaAnterior) {
                previaAnterior.remove();
            }
            var archivo = input.files && input.files[0];
            if (!archivo || archivo.type.indexOf('image/') !== 0) {
                return;
            }
            var lector = new FileReader();
            lector.onload = function (evento) {
                var figura = document.createElement('figure');
                figura.className = 'vista-previa-item vista-previa-item-nueva mb-0';
                var img = document.createElement('img');
                img.src = evento.target.result;
                img.alt = 'Vista previa';
                var pie = document.createElement('figcaption');
                pie.textContent = 'Nueva (sin guardar)';
                figura.appendChild(img);
                figura.appendChild(pie);
                contenedor.appendChild(figura);
            };
            lector.readAsDataURL(archivo);
        });
    });

    // Formulario de copas: las fases de eliminación directa solo tienen sentido en un
    // campeonato. En formato liga el título se decide en la tabla de puntos, así que el
    // bloque se oculta (y al guardar, el servidor tampoco acepta fases — ver admin/torneos.php).
    // Y la configuración de grupos solo aparece en el formato de grupos.
    var grupoFormato = document.getElementById('grupoFormatoTorneo');
    var grupoFases = document.getElementById('grupoFasesPlayoff');
    var grupoGrupos = document.getElementById('grupoConfigGrupos');
    if (grupoFormato && (grupoFases || grupoGrupos)) {
        var actualizarFases = function () {
            var elegido = grupoFormato.querySelector('input[name="modo"]:checked');
            var modo = elegido ? elegido.value : '';
            mostrarBloque(grupoFases, modo !== 'liga');
            mostrarBloque(grupoGrupos, modo === 'grupos');
        };
        grupoFormato.querySelectorAll('input[name="modo"]').forEach(function (radio) {
            radio.addEventListener('change', actualizarFases);
        });
        actualizarFases();
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

    // Copiar el contenido de un campo entero (el mensaje de la jornada para WhatsApp).
    // Distinto del botón de arriba: ahí se copia una URL corta guardada en un atributo,
    // aquí un texto largo que ya está en pantalla y que el organizador puede haber
    // editado antes de copiar.
    document.querySelectorAll('[data-copiar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var campo = document.querySelector(boton.getAttribute('data-copiar'));
            if (!campo) { return; }
            var textoOriginal = boton.innerHTML;
            var marcarCopiado = function () {
                boton.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copiado';
                setTimeout(function () { boton.innerHTML = textoOriginal; }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(marcarCopiado);
            } else {
                campo.select();
                try { document.execCommand('copy'); } catch (e) { /* noop */ }
                marcarCopiado();
            }
        });
    });

    // Campos de solo lectura que se seleccionan enteros al tocarlos (enlaces largos que
    // uno quiere copiar a mano). Va aquí y no como onfocus en el HTML porque la política
    // de seguridad del sitio bloquea el JavaScript escrito dentro de los atributos.
    // Aviso al público de la copa: emergente que se muestra UNA vez por visita. La marca
    // vive en sessionStorage con el hash del texto: si el organizador cambia el mensaje,
    // el hash cambia y el aviso nuevo vuelve a salir. El tono manda: las condolencias van
    // sobrias (sin colores de fiesta), la celebración sí puede ser alegre.
    var avisoCopa = document.getElementById('avisoCopa');
    if (avisoCopa && typeof Swal !== 'undefined') {
        var claveAviso = 'aviso_visto_' + avisoCopa.getAttribute('data-hash');
        var yaVisto = false;
        try { yaVisto = sessionStorage.getItem(claveAviso) === '1'; } catch (e) { /* modo privado */ }
        if (!yaVisto) {
            var tipoAviso = avisoCopa.getAttribute('data-tipo');
            var estilos = {
                luto: { emoji: '🕊️', boton: '#495057', confirmar: 'Nuestro pesar los acompaña' },
                celebracion: { emoji: '🎉', boton: '#7b2ff7', confirmar: '¡Felicidades!' },
                informativo: { emoji: '📢', boton: '#7b2ff7', confirmar: 'Entendido' }
            };
            var estilo = estilos[tipoAviso] || estilos.informativo;
            Swal.fire({
                title: estilo.emoji + ' ' + avisoCopa.getAttribute('data-titulo'),
                text: avisoCopa.getAttribute('data-mensaje'),
                confirmButtonText: estilo.confirmar,
                confirmButtonColor: estilo.boton
            });
            try { sessionStorage.setItem(claveAviso, '1'); } catch (e) { /* noop */ }
        }
    }

    // Aviso de autogol en la ficha de eventos: aparece solo cuando el tipo elegido es
    // "autogol", que es el único caso con regla de captura no obvia (se registra al que
    // la metió en propia y el gol se suma al rival). Mostrarlo siempre sería ruido.
    document.querySelectorAll('select[data-aviso-autogol]').forEach(function (select) {
        var aviso = document.getElementById(select.getAttribute('data-aviso-autogol'));
        if (!aviso) { return; }
        var alternar = function () { aviso.classList.toggle('d-none', select.value !== 'autogol'); };
        select.addEventListener('change', alternar);
        alternar();
    });

    // Selectores que guardan solos al cambiarlos (no tiene sentido un botón "guardar" al
    // lado de una lista de una sola opción). Va en app.js porque el CSP no permite
    // onchange dentro del HTML.
    document.querySelectorAll('select[data-enviar-al-cambiar]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) { select.form.submit(); }
        });
    });

    document.querySelectorAll('[data-seleccionar-al-tocar]').forEach(function (campo) {
        campo.addEventListener('focus', function () { campo.select(); });
        campo.addEventListener('click', function () { campo.select(); });
    });

    // Radios que destapan un bloque del formulario solo cuando son el elegido. Hoy lo usa
    // el nivel "capitán" en Colaboradores, que es el único que necesita además decir de
    // qué equipo. Genérico a propósito: data-muestra="#id" en el radio que lo pide.
    var radiosMuestran = document.querySelectorAll('input[type="radio"][data-muestra]');
    if (radiosMuestran.length) {
        var nombreGrupo = radiosMuestran[0].getAttribute('name');
        var delGrupo = document.querySelectorAll('input[type="radio"][name="' + nombreGrupo + '"]');
        var aplicarMuestra = function () {
            delGrupo.forEach(function (radio) {
                var selector = radio.getAttribute('data-muestra');
                if (selector) {
                    mostrarBloque(document.querySelector(selector), radio.checked);
                }
            });
        };
        delGrupo.forEach(function (radio) {
            radio.addEventListener('change', aplicarMuestra);
        });
        aplicarMuestra();
    }

    // Abrir / cerrar TODAS las jornadas de golpe en la lista de encuentros. Sirve para las
    // dos direcciones: buscar algo a ojo con todo abierto, o volver al orden con todo
    // cerrado después de haber estado abriendo jornadas sueltas.
    document.querySelectorAll('[data-jornadas]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var abrir = boton.getAttribute('data-jornadas') === 'abrir';
            document.querySelectorAll('.jornada-bloque .collapse').forEach(function (bloque) {
                bloque.classList.toggle('show', abrir);
            });
            document.querySelectorAll('.jornada-toggle').forEach(function (t) {
                t.setAttribute('aria-expanded', abrir ? 'true' : 'false');
                t.classList.toggle('collapsed', !abrir);
            });
        });
    });

    // Volver al encuentro que se estaba trabajando. La ficha de eventos y cada acción sobre
    // un partido regresan a la lista con ?ir=<id>: el PHP deja abierta esa jornada y marca
    // la tarjeta, y aquí se baja hasta ella. Sin esto, guardar un evento devolvía al
    // principio de una lista de más de cien encuentros.
    var irA = document.querySelector('[data-ir-a]');
    if (irA) {
        var destino = document.getElementById(irA.getAttribute('data-ir-a'));
        // Un encuentro de playoffs vive en otra pestaña: si no se activa, el navegador
        // intentaría bajar hasta algo que está oculto y no se movería nada.
        var pestana = destino ? destino.closest('.tab-pane') : null;
        if (pestana && !pestana.classList.contains('active')) {
            var disparador = document.querySelector('[data-bs-target="#' + pestana.id + '"]');
            if (disparador && window.bootstrap && window.bootstrap.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(disparador).show();
            }
        }
        if (destino) {
            // En el siguiente frame: el navegador todavía está pintando y una posición
            // calculada ahora se queda corta cuando terminan de cargar logos y tarjetas.
            window.requestAnimationFrame(function () {
                destino.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    }
});
