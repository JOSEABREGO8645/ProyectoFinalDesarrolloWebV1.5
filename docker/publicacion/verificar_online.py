"""Verificación HTTPS de la demostración publicada; secretos solo en archivos privados.

Registra un ingreso de dos unidades, una venta y un traslado de una unidad.
Conserva los UUID en privado para que repetir la prueba no duplique operaciones.
"""
import json
import hashlib
from pathlib import Path
import time
import urllib.request
import urllib.error
import uuid
from concurrent.futures import ThreadPoolExecutor

ROOT = Path(__file__).resolve().parents[2]
PRIVADO = ROOT / 'backend/storage/app/private/publicacion'
URL = 'https://libreria-san-francisco.onrender.com'
accesos = json.loads((PRIVADO / 'storage/app/private/demo/libreria_demo/accesos.json').read_text(encoding='utf-8'))
assert accesos['base'] == 'libreria_demo'
cuenta = next(c for c in accesos['cuentas'] if c['usuario'] == 'admin.demo')
config = json.loads((PRIVADO / 'config.json').read_text(encoding='utf-8'))
archivo = PRIVADO / 'verificacion-online.json'
estado = json.loads(archivo.read_text(encoding='utf-8')) if archivo.exists() else {'url': URL, 'uuid': {t: str(uuid.uuid4()) for t in ['ingresos', 'ventas', 'traslados']}}
assert estado['url'] == URL
archivo.write_text(json.dumps(estado, indent=2) + '\n', encoding='utf-8')
token = None
casos = []


def pedir(ruta, datos=None, esperado=200):
    cab = {'Origin': URL, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json'}
    if token:
        cab['Authorization'] = 'Bearer ' + token
    req = urllib.request.Request(URL + '/api' + ruta, data=json.dumps(datos).encode() if datos is not None else None, headers=cab)
    inicio = time.monotonic()
    try:
        respuesta = urllib.request.urlopen(req, timeout=25)
    except urllib.error.HTTPError as error:
        respuesta = error
    with respuesta:
        codigo = respuesta.code
        contenido = respuesta.read()
        tipo = respuesta.headers.get('Content-Type', '')
    casos.append({'ruta': ruta, 'metodo': 'POST' if datos is not None else 'GET', 'http': codigo, 'segundos': round(time.monotonic() - inicio, 3)})
    if codigo != esperado:
        (PRIVADO / 'verificacion-online-error.json').write_bytes(contenido)
        raise RuntimeError('Estado inesperado en ' + ruta + ': ' + str(codigo) + '; detalle privado conservado.')
    return json.loads(contenido) if 'json' in tipo else contenido


def calentar(url):
    with urllib.request.urlopen(url + '/salud', timeout=55) as respuesta:
        datos = json.load(respuesta)
        assert datos['ok'] and datos['almacen'] == 'mongodb'


try:
    sesion = pedir('/auth/login', {k: cuenta[k] for k in ['usuario', 'password']})
    token = sesion['access_token']
    origen = sesion['usuario']['sucursal_id']
    productos = pedir('/catalogos/productos')
    producto = next(p for p in productos if p['codigo'] == '000-DEMO-001')
    assert producto['nombre'].startswith('DEMO')
    destinos = pedir('/catalogos/destinos')
    destino = next(s['_id'] for s in destinos if s['_id'] != origen)
    item = {'producto_id': producto['_id'], 'cantidad': 1}
    ingreso = pedir('/ingresos', {'operacion_id': estado['uuid']['ingresos'], 'proveedor_id': producto['proveedor_id'], 'motivo': 'Verificacion HTTPS de publicacion: datos ficticios', 'items': [{**item, 'cantidad': 2}]}, 201)
    venta_datos = {'operacion_id': estado['uuid']['ventas'], 'motivo': 'Verificacion HTTPS de publicacion: datos ficticios', 'items': [item], 'codigo': cuenta['codigo'], 'efectivo': '100.00'}
    venta = pedir('/ventas', venta_datos, 201)
    repetida = pedir('/ventas', venta_datos, 201)
    assert venta['_id'] == repetida['_id']
    traslado = pedir('/traslados', {'operacion_id': estado['uuid']['traslados'], 'destino_id': destino, 'motivo': 'Verificacion HTTPS de publicacion: datos ficticios', 'items': [item]}, 201)
    if traslado['estado'] == 'enviado':
        pedir('/traslados/' + traslado['_id'] + '/confirmar', {'motivo': 'Prueba de restriccion de origen'}, 403)
        token = pedir('/auth/sucursal', {'sucursal_id': destino})['access_token']
        traslado = pedir('/traslados/' + traslado['_id'] + '/confirmar', {'motivo': 'Recepcion HTTPS de datos ficticios'})
        assert traslado['estado'] == 'confirmado'
        token = pedir('/auth/sucursal', {'sucursal_id': origen})['access_token']
    movimientos = pedir('/inventario/movimientos?producto_id=' + producto['_id'])
    ids = {d['_id'] for d in [ingreso, venta, traslado]}
    propios = [m for m in movimientos if m['documento_id'] in ids]
    assert len(propios) == 4 and all(m['despues'] >= 0 for m in propios)
    with ThreadPoolExecutor(max_workers=2) as ejecutor:
        list(ejecutor.map(calentar, ['https://libreria-facturacion-demo.onrender.com', 'https://libreria-pagos-demo.onrender.com']))
    integraciones = {}
    for nombre, ruta, documento, endpoint in [('facturacion', '/ventas/' + venta['_id'] + '/factura-prueba', venta, '/v1/facturas'), ('pagos', '/ingresos/' + ingreso['_id'] + '/pago-prueba', ingreso, '/v1/comprobantes-pago')]:
        primero = pedir(ruta, {})
        segundo = pedir(ruta, {})
        assert primero == segundo and primero['estado'] == 'completada'
        payload = {'documento_id': documento['_id'], 'numero': documento['numero'], 'moneda': 'GTQ', 'total_centavos': documento['total_centavos'], 'sucursal': {'id': documento['sucursal_id'], 'nombre': documento['sucursal_nombre']}, 'receptor': {'nombre': documento['cliente_nombre'] if nombre == 'facturacion' else documento['proveedor_nombre'], 'identificador': documento['cliente_nit'] if nombre == 'facturacion' else documento['proveedor_id']}, 'items': [{k: i[k] for k in ['codigo', 'nombre', 'cantidad', 'precio_centavos', 'subtotal_centavos']} for i in documento['items']]}
        cabeceras = {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + config[nombre.upper() + '_API_TOKEN'], 'Idempotency-Key': hashlib.sha256(('libreria:prueba:' + nombre + ':' + documento['_id']).encode()).hexdigest()}
        respuestas = []
        for _ in range(2):
            req = urllib.request.Request(config[nombre.upper() + '_API_URL'] + endpoint, data=json.dumps(payload).encode(), headers=cabeceras)
            with urllib.request.urlopen(req, timeout=25) as respuesta:
                respuestas.append(json.load(respuesta))
        assert respuestas[0] == respuestas[1] and respuestas[0]['referencia'] == primero['referencia']
        integraciones[nombre] = {'estado': primero['estado'], 'idempotencia': True}
    cambio = pedir('/integraciones/tipo-cambio?monto_usd=10.00')
    assert cambio['fuente'] == 'Banco de Guatemala'
    for tipo in ['existencias', 'valoracion', 'kardex', 'ventas-diarias', 'productos-vendidos', 'ventas-clientes', 'ingresos-proveedores', 'flujo-traslados', 'anulaciones']:
        pedir('/reportes/' + tipo)
        assert len(pedir('/reportes/' + tipo + '?formato=csv')) > 20
    informe = {'url': URL, 'alcance': 'HTTPS real con Atlas y datos ficticios', 'procesos': {t: {'numero': d['numero'], 'estado': d['estado']} for t, d in [('ingreso', ingreso), ('venta', venta), ('traslado', traslado)]}, 'movimientos_verificados': len(propios), 'integraciones': integraciones, 'idempotencia_http_directa_proveedores': True, 'banguat': {'consultado': True, 'fecha': cambio['fecha']}, 'casos': casos}
    (ROOT / 'docs/evidencias/publicacion-online.json').write_text(json.dumps(informe, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('HTTPS: ingreso, venta idempotente, traslado confirmado, cuatro movimientos, tres APIs y nueve reportes/CSV correctos.')
finally:
    if token:
        pedir('/auth/logout', {})
