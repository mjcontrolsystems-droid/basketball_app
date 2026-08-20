FROM php:8.3-apache

# libpq-dev es necesario para compilar pdo_pgsql; libcurl4-openssl-dev para curl
# (usado por google_callback.php para hablar con los endpoints de Google OAuth);
# libzip-dev para la extensión zip, que hace falta para leer archivos .xlsx — un Excel
# es en realidad un ZIP con XML adentro (ver app/Support/importacion.php).
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql fileinfo curl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Sirve public/ como raíz web y manda todo al front controller, que resuelve el slug
# de la copa (/slug/...) y elige el controlador.
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Por defecto PHP solo acepta subidas de 2MB, pero una foto tomada con la cámara
# de un celular fácilmente pesa varios MB más que eso.
RUN { \
        echo 'upload_max_filesize = 12M'; \
        echo 'post_max_size = 13M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html/
WORKDIR /var/www/html

# El DocumentRoot es /var/www/html/public (ver apache-vhost.conf): el código de la
# aplicación vive un nivel arriba y no es accesible por web.

# Apache (en vez del servidor embebido de PHP) maneja varias peticiones en paralelo,
# necesario para no colapsar con tráfico concurrente.
# Render inyecta el puerto real a usar en la variable de entorno PORT; el reemplazo
# se hace al arrancar el contenedor porque el valor no se conoce en tiempo de build.
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "sed -ri \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -ri \"s/:80/:${PORT}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
