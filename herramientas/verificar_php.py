"""Verificador de balance para PHP.

El anterior contaba caracteres a lo bruto, así que un paréntesis dentro de una cadena o
de un comentario le daba falsos positivos (y podía tapar uno real). Este recorre el
archivo carácter por carácter llevando el estado: código, cadena simple, cadena doble,
comentario de línea, comentario de bloque, heredoc y modo HTML. Solo cuenta lo que está
en CÓDIGO.
"""
import sys, re

def escanear(src):
    i, n = 0, len(src)
    estado = 'html'
    pila = []          # pila de aperturas en código
    heredoc_fin = None
    errores = []
    linea = 1
    conteo = {'(':0, ')':0, '{':0, '}':0, '[':0, ']':0}
    pareja = {')':'(', '}':'{', ']':'['}

    while i < n:
        c = src[i]
        if c == '\n':
            linea += 1

        if estado == 'html':
            if src.startswith('<?php', i) or src.startswith('<?=', i):
                estado = 'codigo'
                i += 5 if src.startswith('<?php', i) else 3
                continue
            i += 1; continue

        if estado == 'codigo':
            if src.startswith('?>', i):
                estado = 'html'; i += 2; continue
            if src.startswith('//', i) or c == '#':
                estado = 'linea'; i += 1; continue
            if src.startswith('/*', i):
                estado = 'bloque'; i += 2; continue
            if c == "'":
                estado = 'simple'; i += 1; continue
            if c == '"':
                estado = 'doble'; i += 1; continue
            if src.startswith('<<<', i):
                m = re.match(r"<<<\s*(['\"]?)([A-Za-z_]\w*)\1", src[i:])
                if m:
                    heredoc_fin = m.group(2); estado = 'heredoc'; i += m.end(); continue
            if c in '([{':
                conteo[c] += 1; pila.append((c, linea)); i += 1; continue
            if c in ')]}':
                conteo[c] += 1
                if not pila:
                    errores.append(f'línea {linea}: sobra "{c}"')
                else:
                    abre, ln = pila.pop()
                    if abre != pareja[c]:
                        errores.append(f'línea {linea}: "{c}" no cierra el "{abre}" de la línea {ln}')
                i += 1; continue
            i += 1; continue

        if estado == 'linea':
            if c == '\n' or src.startswith('?>', i):
                estado = 'codigo' if c == '\n' else 'codigo'
                if c == '\n': i += 1
                continue
            i += 1; continue

        if estado == 'bloque':
            if src.startswith('*/', i): estado = 'codigo'; i += 2; continue
            i += 1; continue

        if estado in ('simple', 'doble'):
            if c == '\\': i += 2; continue
            if (estado == 'simple' and c == "'") or (estado == 'doble' and c == '"'):
                estado = 'codigo'
            i += 1; continue

        if estado == 'heredoc':
            if c == '\n':
                resto = src[i+1:]
                m = re.match(r'[ \t]*' + re.escape(heredoc_fin) + r'\b', resto)
                if m:
                    estado = 'codigo'; i += 1 + m.end(); continue
            i += 1; continue

    for abre, ln in pila:
        errores.append(f'línea {ln}: quedó sin cerrar "{abre}"')
    return errores, conteo

def alternativa(src):
    """if/endif, foreach/endforeach... en las plantillas."""
    problemas = []
    for kw, fin in [('if','endif'), ('foreach','endforeach'), ('for','endfor'), ('while','endwhile'), ('switch','endswitch')]:
        abiertos = 0
        for m in re.finditer(r'\b' + kw + r'\s*\(', src):
            if kw == 'for' and re.match(r'\bforeach', src[m.start():]): continue
            j = m.end(); d = 1
            while j < len(src) and d > 0:
                if src[j] == '(': d += 1
                elif src[j] == ')': d -= 1
                j += 1
            k = j
            while k < len(src) and src[k] in ' \t\r\n': k += 1
            if k < len(src) and src[k] == ':': abiertos += 1
        cierres = len(re.findall(r'\b' + fin + r'\s*;', src))
        if abiertos != cierres:
            problemas.append(f'{kw}/{fin}: {abiertos} abiertos, {cierres} cerrados')
    return problemas

if __name__ == '__main__':
    malos = 0
    for ruta in sys.argv[1:]:
        src = open(ruta, encoding='utf-8').read()
        errores, _ = escanear(src)
        errores += alternativa(src)
        if errores:
            malos += 1
            print('MAL ', ruta)
            for e in errores[:5]: print('        ', e)
        else:
            print('OK  ', ruta)
    sys.exit(1 if malos else 0)
