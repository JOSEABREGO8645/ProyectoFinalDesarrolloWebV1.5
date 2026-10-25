"""Prueba de empaquetado local contra datos ficticios; no despliega en Internet."""
import http.client
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import time
from urllib.parse import urlsplit, urlunsplit, parse_qsl, urlencode

ROOT = Path(__file__).resolve().parents[2]
PRIVADO = ROOT / 'backend/storage/app/private/publicacion'
env = (ROOT / 'backend/.env').read_text(encoding='utf-8')
uri = re.search(r'^DB_URI\s*=\s*[\"\']?([^\r\n\"\']+)', env, re.M).group(1)
url = urlsplit(uri)
if url.hostname not in ['localhost', '127.0.0.1'] or url.port != 27018:
    raise SystemExit('Esta prueba exige MongoDB local en 27018; no admite bases remotas.')
auth = url.netloc.rsplit('@', 1)[0] + '@' if '@' in url.netloc else ''
query = dict(parse_qsl(url.query)); query.update(directConnection='true', replicaSet='rs0')
uri = urlunsplit((url.scheme, auth + 'host.docker.internal:27018', url.path, urlencode(query), ''))
config = json.loads((PRIVADO / 'config.json').read_text(encoding='utf-8'))
accesos = json.loads((ROOT / 'backend/storage/app/private/demo/libreria_demo/accesos.json').read_text(encoding='utf-8'))
assert accesos['base'] == 'libreria_demo'
nombre = 'libreria-publicacion-prueba-' + secrets.token_hex(4)
archivo_env = PRIVADO / (nombre + '.env')
valores = {'APP_KEY': config['APP_KEY'], 'JWT_SECRET': config['JWT_SECRET'], 'DB_URI': uri, 'APP_URL': 'http://127.0.0.1:18080', 'DB_DATABASE': 'libreria_demo'}
archivo_env.write_text(''.join(k + '=' + v + '\n' for k, v in valores.items()), encoding='utf-8')
resultados = []
def docker(*args, comprobar=True):
    r = subprocess.run(['docker', *args], cwd=ROOT, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if comprobar and r.returncode:
        (PRIVADO / 'prueba-imagen.log').write_text(r.stdout + r.stderr, encoding='utf-8')
        raise RuntimeError('Falló Docker; registro en el directorio privado de publicación.')
    return r.stdout.strip()
def pedir(ruta, metodo='GET', datos=None, token=None, cookie=None):
    conn = http.client.HTTPConnection('127.0.0.1', 18080, timeout=15)
    cab = {'Origin': valores['APP_URL'], 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    if token: cab['Authorization'] = 'Bearer ' + token
    if cookie: cab['Cookie'] = cookie
    cuerpo = None
    if datos is not None: cuerpo = json.dumps(datos); cab['Content-Type'] = 'application/json'
    conn.request(metodo, ruta, cuerpo, cab)
    r = conn.getresponse(); contenido = r.read(); cabeceras = dict(r.getheaders()); estado = r.status
    conn.close()
    return estado, contenido, cabeceras
def correcto(nombre_caso, condicion):
    if not condicion: raise AssertionError('No pasó: ' + nombre_caso)
    resultados.append({'caso': nombre_caso, 'resultado': 'correcto'})
def esperar():
    for _ in range(60):
        try:
            if pedir('/up')[0] == 200: return
        except (OSError, http.client.HTTPException): pass
        time.sleep(.5)
    raise RuntimeError('No respondió el contenedor. Revise el registro privado de Docker.')
try:
    docker('run', '-d', '--name', nombre, '--label', 'libreria.prueba=publicacion', '-p', '127.0.0.1:18080:10000', '--env-file', str(archivo_env), 'libreria:publicacion-prueba')
    esperar()
    correcto('Vue compilado en /', b'type="module"' in pedir('/')[1])
    correcto('API sin sesión devuelve 401 JSON', pedir('/api/auth/me')[0] == 401)
    cuenta = next(c for c in accesos['cuentas'] if c['usuario'] == 'admin.demo')
    estado, cuerpo, cab = pedir('/api/auth/login', 'POST', {k: cuenta[k] for k in ['usuario', 'password']})
    correcto('Login contra MongoDB desde Linux', estado == 200)
    datos = json.loads(cuerpo); token = datos['access_token']
    cookie = cab['Set-Cookie'].split(';', 1)[0]
    correcto('Cookie de publicación Secure y HttpOnly', 'secure' in cab['Set-Cookie'].lower() and 'httponly' in cab['Set-Cookie'].lower())
    for ruta in ['/api/auth/me', '/api/dashboard', '/api/productos', '/api/ingresos', '/api/ventas', '/api/traslados']:
        correcto(ruta, pedir(ruta, token=token)[0] == 200)
    correcto('ID inexistente', pedir('/api/ventas/' + 'f' * 24, token=token)[0] == 404)
    correcto('Validación sin escribir inventario', pedir('/api/ventas', 'POST', {'items': []}, token=token)[0] == 422)
    for reporte in ['existencias', 'valoracion', 'kardex', 'ventas-diarias', 'productos-vendidos', 'ventas-clientes', 'ingresos-proveedores', 'flujo-traslados', 'anulaciones']:
        correcto('Reporte ' + reporte, pedir('/api/reportes/' + reporte, token=token)[0] == 200)
    inexistente = 'no-existe-' + secrets.token_hex(4)
    for _ in range(5): correcto('Acceso inválido', pedir('/api/auth/login', 'POST', {'usuario': inexistente, 'password': 'ClaveFicticiaDePruebas2026!'})[0] == 401)
    correcto('Límite de acceso', pedir('/api/auth/login', 'POST', {'usuario': inexistente, 'password': 'ClaveFicticiaDePruebas2026!'})[0] == 429)
    docker('restart', '-t', '5', nombre); esperar()
    correcto('Sesión conservada tras reinicio', pedir('/api/auth/me', token=token)[0] == 200)
    correcto('Límite conservado tras reinicio', pedir('/api/auth/login', 'POST', {'usuario': inexistente, 'password': 'ClaveFicticiaDePruebas2026!'})[0] == 429)
    estado, cuerpo, cab = pedir('/api/auth/refresh', 'POST', {}, cookie=cookie)
    correcto('Renovación conservada tras reinicio', estado == 200)
    token = json.loads(cuerpo)['access_token']; cookie = cab['Set-Cookie'].split(';', 1)[0]
    correcto('Cerrar sesión', pedir('/api/auth/logout', 'POST', {}, token=token, cookie=cookie)[0] == 200)
    correcto('Renovación revocada', pedir('/api/auth/refresh', 'POST', {}, cookie=cookie)[0] == 401)
    postman = subprocess.run(['node', 'tests/demostracion.postman.mjs'], cwd=ROOT / 'frontend', env=dict(os.environ, E2E_API_URL=valores['APP_URL'] + '/api', E2E_CUENTAS=json.dumps(accesos['cuentas']), E2E_POSTMAN_REPORTE='publicacion-postman.json'), capture_output=True, text=True, encoding='utf-8', errors='replace')
    if postman.returncode:
        (PRIVADO / 'prueba-postman.log').write_text(postman.stdout + postman.stderr, encoding='utf-8')
    correcto('Colección Postman de demostración en Linux', postman.returncode == 0)
    navegador = subprocess.run(['node', 'docker/publicacion/navegador.mjs'], cwd=ROOT, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if navegador.returncode:
        (PRIVADO / 'prueba-navegador.log').write_text(navegador.stdout + navegador.stderr, encoding='utf-8')
    correcto('Navegador con Vue compilado y renovación', navegador.returncode == 0)
    (ROOT / 'docs/evidencias/publicacion-local.json').write_text(json.dumps({'alcance': 'Imagen Linux local, datos ficticios, HTTP loopback; no verifica el HTTPS público de Render/Atlas.', 'casos': resultados}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('Imagen local verificada:', len(resultados), 'comprobaciones. No se modificó inventario ni se publicaron recursos.')
except Exception:
    (PRIVADO / 'prueba-apache.log').write_text(docker('logs', nombre, comprobar=False), encoding='utf-8')
    raise
finally:
    docker('rm', '-f', nombre, comprobar=False)
    archivo_env.unlink(missing_ok=True)
