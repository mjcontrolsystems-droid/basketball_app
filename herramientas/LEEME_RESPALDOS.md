# Respaldos de la base de datos

La base vive en Neon (plan gratuito), que **no** guarda historial para volver atrás. Si
algo se borra o se corrompe, lo único que hay es lo que esté en la carpeta `Respaldos BD`.

## Los tres botones

| Doble clic en | Qué hace | Cada cuánto |
|---|---|---|
| `programar_respaldo.bat` | Deja el respaldo corriendo solo todos los días a las 20:00 | **Una sola vez** |
| `respaldar_base.bat` | Respalda ahora mismo | Cuando quieras uno extra |
| `verificar_respaldo.bat` | Comprueba que el último respaldo se pueda restaurar | **Una vez al mes** |

## Empieza por aquí

1. Doble clic en **`programar_respaldo.bat`**. Con eso queda automático.
2. Doble clic en **`verificar_respaldo.bat`** para comprobar que el respaldo sirve.

Si el segundo dice **"ESTE RESPALDO SIRVE"**, ya estás cubierto.

## Por qué hay que verificar y no solo respaldar

Un respaldo puede pesar lo esperado, tener buena fecha y estar cortado por la mitad. O
traer las tablas vacías porque ese día la conexión apuntaba a otro lado. Eso no se nota
mirando la carpeta: se nota el día que hace falta restaurar, que es el peor día posible
para enterarse.

`verificar_respaldo.bat` descomprime el archivo entero —lo que obliga a leer cada bloque
de datos— y cuenta las filas de cada tabla. Si alguna tabla importante viene vacía, avisa.
**No se conecta a la base de la liga**, así que se puede correr a mitad de temporada sin
ningún riesgo.

## Dónde ver que está funcionando

- **Los archivos:** carpeta `Respaldos BD` del proyecto (se sincroniza con OneDrive, así
  que también quedan fuera de esta computadora).
- **El detalle de cada corrida:** `Respaldos BD\registro.txt`. Una línea por corrida, con
  fecha y resultado. Si el respaldo automático lleva días fallando, ahí se ve.

Los respaldos con más de 90 días se borran solos para que la carpeta no crezca sin fin.

## Si algún día hay que restaurar de verdad

```
pg_restore --clean --no-owner -d "URL_DE_LA_BASE" "Respaldos BD\liga_FECHA.dump"
```

**Ese comando REEMPLAZA todo lo que haya en la base de destino.** Antes de correrlo:

1. Haz un respaldo del estado actual, aunque esté mal — puede tener datos que el respaldo
   viejo no tiene.
2. Asegúrate de que la URL es la que crees que es.
3. Si hay dudas, restaura primero en una base nueva y vacía, revisa, y recién después
   decide.

## Para quitar el respaldo automático

```
programar_respaldo.bat quitar
```
