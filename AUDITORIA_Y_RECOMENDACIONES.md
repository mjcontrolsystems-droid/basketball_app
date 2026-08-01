# Auditoría completa y recomendaciones — Plataforma de Copas y Ligas

Fecha: agosto 2026

Revisé la aplicación de pies a cabeza: las 14 páginas públicas, las 10 del panel admin, los 16 includes, el esquema de base de datos, el CSS y el JavaScript. Este documento resume el estado actual, lo que corregí en esta pasada y lo que recomiendo para llevarla a nivel completamente profesional.

## Veredicto general

La base es sólida. La app ya tiene cosas que muchos proyectos profesionales descuidan: protección CSRF en todos los formularios, límites de intentos contra fuerza bruta, escapado de HTML consistente, OAuth con validación de state, verificación real de tipo MIME en las subidas (con SVG excluido a propósito por riesgo XSS), aislamiento de datos por usuario, cabeceras de seguridad (CSP, HSTS, X-Frame-Options) y un modelo multi-torneo bien pensado. El diseño visual es coherente y los textos están cuidados.

Lo que le falta para "terminar de ser profesional" no es una reescritura: son capas de pulido, confiabilidad y presencia que se detallan abajo.

## Corregido en esta pasada

**Seguridad — escalada a super-admin (grave).** El rol de super-admin se define por correo. En "Mi Perfil" cualquier usuario podía cambiar su correo a uno de los correos super-admin y, si esa cuenta no existía aún, quedarse con el control de la lista blanca y los cupos. Ahora los correos de `SUPERADMIN_EMAILS` están reservados: nadie puede asignárselos desde el perfil.

**Compartir en redes (Open Graph).** Al compartir el link de una copa por WhatsApp aparecía un link pelón. Ahora aparece una tarjeta con el nombre de la copa, su descripción y su logo. La primera impresión del torneo empieza en el chat del grupo.

**Barra del navegador con el color de la copa** (`theme-color`): detalle de app nativa en el teléfono.

**robots.txt.** Los buscadores indexan el sitio público pero no el panel admin ni las rutas de login.

**Botón de Jugadores visible.** La plantilla de jugadores solo se alcanzaba por un icono sin texto dentro de Equipos. Ahora es un botón con etiqueta — es el paso natural después de crear un equipo y no debe estar escondido.

**Además, de pasadas anteriores de esta sesión:** marcador auto-calculado desde los goles, modal de fecha futura, filtro de jugadores por equipo compatible con iPhone, sistema de cupos por organizador, y correcciones responsive del panel en móvil.

## Recomendaciones para nivel profesional

### Prioridad alta — confiabilidad y negocio

**1. Respaldos automáticos de la base de datos.** Todo el negocio vive en una sola base Postgres (Neon). Si se corrompe o alguien borra una copa por error, no hay vuelta atrás. Neon tiene restauración a un punto en el tiempo en planes de pago; como mínimo, programa un `pg_dump` semanal guardado en otro lado. Es la inversión con mejor relación costo/riesgo de toda esta lista.

**2. Plan de hosting.** El plan gratuito de Render apaga el servidor tras 15 minutos sin visitas; la primera visita después lo espera 30-60 segundos. Para una demo está bien; para clientes que pagan por torneo, esa espera parece que "el sitio no sirve". El plan Starter (~$7/mes) lo mantiene siempre encendido.

**3. Dominio propio.** `basketball-app-qlqp.onrender.com` no transmite marca. Un dominio como `mjcontrolsystems.com` (~$12/año) con subdominio por producto se conecta en Render en minutos y resuelve además el problema del nombre viejo en la URL.

**4. Registro de pagos dentro de la app.** Ya existe el cupo por torneo; el siguiente paso natural es una tabla simple de pagos (quién, cuánto, cuándo, por qué torneo) visible solo para super-admins. Sin eso, el control del dinero queda en tu memoria o en un cuaderno aparte.

### Prioridad media — experiencia de usuario

**5. Correos transaccionales.** Hoy, cuando autorizas un correo, tienes que avisarle por WhatsApp. Un correo automático de "Ya puedes crear tu cuenta" (con un servicio como Resend o Brevo, gratis en volúmenes bajos) hace ver la plataforma seria. Lo mismo para "tu cupo fue ampliado".

**6. Recuperación de contraseña.** Las cuentas con usuario/contraseña no tienen "olvidé mi contraseña". Hoy lo resuelves tú a mano. Depende del punto 5 (necesita poder enviar correos).

**7. Estadísticas de visitas por copa.** Los organizadores (tus clientes) querrán saber cuánta gente ve su torneo. Un contador simple de visitas por copa/día, mostrado en su dashboard, es un argumento de venta ("tu copa tuvo 1,200 visitas esta semana").

**8. Imagen para compartir resultados.** Después de cada partido, los organizadores suelen armar una imagen del marcador en Canva para Instagram. Generar esa imagen automáticamente (marcador, escudos, colores de la copa) con un botón "Descargar imagen del resultado" ahorraría a cada cliente su tarea más repetitiva.

### Prioridad baja — pulido técnico

**9. Optimización de imágenes.** Las subidas se guardan tal cual (hasta 10MB). Redimensionar en el servidor a un máximo razonable (p. ej. 800px) con GD haría el sitio notablemente más rápido en datos móviles.

**10. Página 404 con marca.** Los errores muestran texto plano. Una página de error con el diseño del sitio es un detalle que los clientes sí notan.

**11. Pruebas automatizadas.** No hay tests. Un puñado de pruebas para lo que toca dinero (cupos) y clasificación (cálculo de tabla) evitaría regresiones al seguir agregando funciones.

**12. Monitoreo de errores.** Los errores van al log de Render y nadie los mira. Un servicio gratuito como Sentry avisa por correo cuando algo falla en producción, antes de que lo reporte un cliente.

## Pendientes operativos (no son código)

- Correr `php scripts/migrar_cupos_torneos.php` en producción para crear la columna de cupos.
- Hacer `git push origin main` (hay commits locales sin subir; este entorno no tiene credenciales de GitHub).
- En Render, verificar que el servicio siga conectado al repo renombrado `web-control-deportivo`.
- Si se cambia la URL pública, actualizar el redirect URI en Google Cloud Console o el login con Google dejará de funcionar.
