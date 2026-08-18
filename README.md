# Copa Estrellas — Liga Femenina de Basketball

Plataforma de gestión y sitio público para un campeonato femenino de basketball: tabla de posiciones, calendario de encuentros, perfiles de equipo, patrocinadores, perfil del organizador con comentarios anónimos, y un panel de administración completo.

Los datos (equipos, partidos, patrocinadores, comentarios, torneo, organizador) se guardan en una base de datos **PostgreSQL**. Las imágenes subidas (escudos, logos, foto de perfil) se guardan como datos binarios dentro de la misma base de datos — así todo persiste correctamente incluso en hostings sin disco persistente como Render.

## Requisitos

- PHP **8.1 o superior** (usa `match`, `str_starts_with`, tipado estricto)
- Extensión `pdo_pgsql` habilitada
- Una base de datos PostgreSQL accesible por internet (ver sección Neon abajo)

## Correr en local

1. Copia `.env.example` a `.env` y pon tu connection string real de PostgreSQL:
   ```
   DATABASE_URL=postgresql://usuario:password@host.neon.tech/basedatos?sslmode=require
   ```
2. Crea las tablas y (opcionalmente) migra los datos de ejemplo:
   ```
   php scripts/migrar_json_a_postgres.php
   ```
3. Levanta el servidor (con `-t public`, que es la raíz web):
   ```
   php -S localhost:8000 -t public router.php
   ```

### Probar contra una base local (sin tocar producción)

```
docker run -d --name copa-pg -e POSTGRES_PASSWORD=copa -e POSTGRES_USER=copa \
  -e POSTGRES_DB=copa_test -p 55432:5432 postgres:16-alpine
docker exec -i copa-pg psql -U copa -d copa_test < schema.sql

export DATABASE_URL="postgresql://copa:copa@127.0.0.1:55432/copa_test?sslmode=disable"
php scripts/seed_pruebas.php        # datos de ejemplo: una liga y un campeonato
php -S localhost:8000 -t public router.php
```

El seed crea dos torneos que cubren los caminos que se comportan distinto (liga a ida y
vuelta en fútbol, y campeonato con playoffs en basketball) y una cuenta de prueba
(`prueba` / `prueba123`). Se niega a correr si `DATABASE_URL` no apunta a `copa_test`.

## Acceso al panel del organizador

- URL: `/login.php`
- Usuario: `admin`
- Contraseña: `Estrellas2026`

⚠️ **Cambia esta contraseña desde "Mi Perfil" antes de publicar el sitio.**

## Estructura del proyecto

```
schema.sql               Esquema de la base de datos PostgreSQL
public/                  ÚNICO directorio accesible desde la web (DocumentRoot)
  index.php              Front controller: recibe todas las peticiones y despacha
  assets/                CSS y JS (las imágenes van en la base de datos, no aquí)

app/
  Controllers/           Reciben la petición, deciden qué hacer y llaman a una vista
    Publico/             Sitio público de cada copa (inicio, tabla, calendario, en vivo...)
    Admin/               Panel del organizador (exige sesión)
    Auth/                Login, registro, recuperación de contraseña, Google
  Models/                Acceso a datos, uno por entidad (Torneo, Equipo, Partido...)
  Views/                 Plantillas: solo presentación, no consultan la base
    layouts/             Navbar/footer del sitio y sidebar del panel
    parciales/           Trozos reutilizables (tarjeta de encuentro, modales)
    publico/ admin/ auth/
  Support/               Infraestructura y reglas del dominio
                         bd, auth, vista, helpers, upload, correo, filtro,
                         liga (reglas por deporte), tabla (posiciones), fixture

config/
  config.php             Constantes, sesión, cabeceras de seguridad, url()
  bootstrap.php          Arranque: carga soporte y modelos
  rutas.php              Mapa de URL → controlador

schema.sql               Esquema de la base de datos PostgreSQL
scripts/                 Migraciones, datos de prueba y pruebas automatizadas
```

### Cómo fluye una petición

```
GET /liga-municipal/tabla.php
   │
   ├─ Apache (o router.php en local) → public/index.php
   ├─ front controller: separa el slug "liga-municipal" de la ruta "tabla.php",
   │  busca la copa y la deja disponible con copa_actual()
   ├─ config/rutas.php: tabla.php → Controllers/Publico/Tabla.php
   ├─ el controlador pide datos a los modelos y calcula lo que hace falta
   └─ vista_publica('publico/tabla', [...]) pinta layout + plantilla
```

Las URLs terminadas en `.php` **no** corresponden a archivos reales: se mantienen así a
propósito porque hay códigos QR impresos y enlaces compartidos apuntando a ellas.

## Base de datos gratuita: Neon

1. Crea una cuenta gratis en [neon.tech](https://neon.tech) y un proyecto nuevo.
2. Copia el **Connection string** que te dan (empieza con `postgresql://...`) — lo vas a necesitar tanto en local (`.env`) como en Render.
3. El plan gratuito de Neon no tiene fecha de expiración fija (a diferencia de la Postgres gratuita de Render, que se borra a los 90 días); solo se "duerme" tras un rato sin uso y despierta sola con la siguiente visita.

## Desplegar en Render (gratis)

Este proyecto incluye un `Dockerfile` porque Render no ejecuta PHP de forma nativa.

1. En [render.com](https://render.com), crea una cuenta (puedes usar tu GitHub) y conecta el repositorio `basketball_app`.
2. **New > Web Service**, selecciona el repo, y Render detectará el `Dockerfile` automáticamente (Runtime: Docker).
3. Plan: **Free**.
4. En "Environment", agrega la variable:
   - `DATABASE_URL` = tu connection string de Neon
5. Deploy. La primera vez que despliegues, entra una sola vez por SSH/Shell de Render (o corre el script en local apuntando a la misma base) para ejecutar:
   ```
   php scripts/migrar_json_a_postgres.php
   ```
   Esto crea las tablas. Si ya lo corriste en local contra la misma base de datos de Neon, no hace falta repetirlo.
6. Entra a `https://tu-servicio.onrender.com/login.php` y **cambia la contraseña por defecto**.

### Nota sobre el plan gratuito de Render

El servicio se "duerme" tras ~15 minutos sin visitas y tarda unos segundos en despertar con la siguiente visita — normal en el plan gratuito, no es un error.

## Pruebas

```
php scripts/tests.php
```

Cubren la lógica donde un error pasa desapercibido más tiempo y hace más daño: cálculo de
la tabla de posiciones, marcador derivado de los eventos, reglas de cada deporte,
cronómetro con tiempo extra, formato liga vs campeonato, alineaciones y el generador de
calendario. No necesitan base de datos ni servidor.
