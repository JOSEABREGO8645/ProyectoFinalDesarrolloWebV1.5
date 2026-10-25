"""Configura en privado la publicación. No crea cuentas ni recursos de pago."""
import argparse
import base64
import json
import os
from pathlib import Path
import secrets
import subprocess
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[2]
BACKEND = ROOT / 'backend'
PRIVADO = BACKEND / 'storage/app/private/publicacion'
CONFIG = PRIVADO / 'config.json'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--preparar', action='store_true', help='Crear una configuración privada si no existe.')
parser.add_argument('--exportar', action='store_true', help='Exportar variables privadas para los paneles de Render.')
parser.add_argument('--cargar', action='store_true', help='Cargar datos ficticios en libreria_demo de Atlas, conservando la base local.')
args = parser.parse_args()
if not any(vars(args).values()):
    parser.print_help()
    raise SystemExit(0)
PRIVADO.mkdir(parents=True, exist_ok=True)
if args.preparar and not CONFIG.exists():
    datos = {'version': 1, 'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(), 'JWT_SECRET': secrets.token_hex(48), 'DB_URI': '', 'FACTURACION_API_TOKEN': secrets.token_hex(32), 'PAGOS_API_TOKEN': secrets.token_hex(32), 'FACTURACION_API_URL': '', 'PAGOS_API_URL': '', 'API_FACTURACION_MONGO_URI': '', 'API_PAGOS_MONGO_URI': ''}
    with CONFIG.open('x', encoding='utf-8') as archivo:
        json.dump(datos, archivo, indent=2)
        archivo.write('\n')
if not CONFIG.exists():
    raise SystemExit('Ejecute primero --preparar y complete el archivo privado.')
datos = json.loads(CONFIG.read_text(encoding='utf-8'))
if datos.get('version') != 1:
    raise SystemExit('Versión de configuración no reconocida.')


def atlas(uri):
    try:
        destino = urlsplit(uri)
        return destino.scheme == 'mongodb+srv' and (destino.hostname or '').endswith('.mongodb.net') and bool(destino.username) and bool(destino.password)
    except ValueError:
        return False


if args.cargar:
    if not atlas(datos.get('DB_URI', '')):
        raise SystemExit('Complete DB_URI con la URI privada mongodb+srv de su clúster Atlas. No se admite apuntar a la base local.')
    storage = PRIVADO / 'storage'
    for ruta in ['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']:
        (storage / ruta).mkdir(parents=True, exist_ok=True)
    entorno = dict(os.environ, APP_ENV='local', APP_DEBUG='false', DB_CONNECTION='mongodb', DB_URI=datos['DB_URI'], DB_DATABASE='libreria_preparacion', JWT_SECRET=datos['JWT_SECRET'], APP_KEY=datos['APP_KEY'], CACHE_STORE='file', CACHE_PREFIX='publicacion-preparacion:', LARAVEL_STORAGE_PATH=str(storage), APP_CONFIG_CACHE=str(storage / 'framework/config.php'))
    salida = subprocess.run(['php', 'artisan', 'libreria:demo', '--no-interaction'], cwd=BACKEND, env=entorno, capture_output=True)
    (PRIVADO / 'preparacion.log').write_bytes(salida.stdout + salida.stderr)
    if salida.returncode:
        raise SystemExit('No se completó la preparación. Revise el registro privado en backend/storage/app/private/publicacion/preparacion.log.')
    print('Datos ficticios preparados en Atlas. Accesos privados: backend/storage/app/private/publicacion/storage/app/private/demo/libreria_demo/accesos.json')
if args.exportar:
    claves = ['APP_KEY', 'JWT_SECRET', 'DB_URI', 'FACTURACION_API_URL', 'FACTURACION_API_TOKEN', 'PAGOS_API_URL', 'PAGOS_API_TOKEN']
    if not all(datos.get(k) for k in claves):
        raise SystemExit('Faltan URI o URLs reales en la configuración privada.')
    if not atlas(datos['DB_URI']):
        raise SystemExit('DB_URI debe apuntar al clúster Atlas de demostración.')
    if any('\n' in str(v) or '\r' in str(v) for v in datos.values()):
        raise SystemExit('La configuración contiene saltos de línea no permitidos.')
    for clave in ['FACTURACION_API_URL', 'PAGOS_API_URL']:
        url = urlsplit(datos[clave])
        if url.scheme != 'https' or not (url.hostname or '').endswith('.onrender.com'):
            raise SystemExit('Use las URLs HTTPS reales de los proveedores desplegados en Render.')
        if url.path not in ['', '/'] or url.query or url.fragment or url.username:
            raise SystemExit('Las URL de proveedores deben ser bases sin rutas, credenciales, consultas ni fragmentos. El cliente agrega el endpoint.')
    for tipo in ['FACTURACION', 'PAGOS']:
        if not atlas(datos.get('API_' + tipo + '_MONGO_URI', '')):
            raise SystemExit('Complete las URI privadas de Atlas para los dos proveedores.')
    def exportar(nombre, valores):
        (PRIVADO / nombre).write_text(''.join(k + '=' + str(v) + '\n' for k, v in valores.items()), encoding='utf-8')
    exportar('backend.env', {k: datos[k] for k in claves})
    for tipo in ['FACTURACION', 'PAGOS']:
        exportar(tipo.lower() + '.env', {'API_TOKEN': datos[tipo + '_API_TOKEN'], 'API_MONGO_URI': datos['API_' + tipo + '_MONGO_URI']})
    print('Variables exportadas únicamente en backend/storage/app/private/publicacion/. No comparta estos archivos en Git ni en el chat.')
print('Configuración privada conservada en backend/storage/app/private/publicacion/config.json')
