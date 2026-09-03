# Pruebas de la lógica de la liga

Comprueban que las cuatro reglas que **no se ven en pantalla** sigan dando el resultado
correcto: tabla de posiciones, suspensiones por tarjetas, vigencia de las multas y motor
de calendario. Son las que, si fallan, no avisan — se descubren cuando un capitán reclama.

## Cómo correrlas

**Doble clic en `Probar.bat`.** Si aparece "TODO BIEN", el cambio se puede subir.

Desde la consola:

```
php herramientas/pruebas/correr.php
```

## Qué NO hacen

No tocan la base de datos, no abren sesión y no leen el `.env`. Solo cargan los archivos
de reglas y les pasan datos inventados. Se pueden correr en cualquier momento, incluso a
mitad de temporada, sin riesgo de escribir nada en ningún lado.

## Cuándo correrlas

- Antes de cada `git push`, sobre todo si se tocó el calendario, las sanciones o la tabla.
- Después de cambiar una regla de la liga (montos, partidos de suspensión, puntos).
- Cuando algo "se ve raro" en producción: si una prueba falla, ahí está el problema.

## Cómo agregar una prueba

Cada archivo de `casos/` es un tema. Se agrega un caso así:

```php
prueba('lo que debería pasar, dicho en una frase', function () {
    igual(3, calcular_algo(1, 2), 'una pista de qué salió mal');
    cierto($condicion, 'lo que se esperaba');
});
```

Las funciones disponibles son `igual()`, `cierto()`, `falso()` y `grupo()` para separar
secciones en la salida.

**Regla de oro:** cuando aparezca un error en producción, antes de arreglarlo se escribe
la prueba que lo reproduce. Así ese error concreto no vuelve nunca.

## Qué se cubre hoy

| Archivo | Regla |
|---|---|
| `01_tabla.php` | Puntos, W.O. en la tabla, playoffs excluidos, desempates |
| `02_marcador.php` | Marcador desde la ficha, autogol, basketball, portería menos vencida |
| `03_sanciones.php` | La multa de la jornada N se cobra desde la N+1 |
| `04_suspensiones.php` | Roja, acumulación de amarillas, ventana de castigo |
| `05_calendario.php` | Sin cruces repetidos, fechas excluidas, cierre en la fecha de la final |
| `06_jornadas.php` | Jornada deducida de la fecha, tope al corregirla a mano |
| `07_capitan.php` | Qué alcanza el nivel capitán y que no salga de su equipo |
| `08_mensaje.php` | El texto de la jornada que se pega en el grupo de WhatsApp |

Lo que todavía **no** se cubre: importación de plantillas, permisos por nivel, correos y
generación de PDFs. Son los siguientes candidatos.
