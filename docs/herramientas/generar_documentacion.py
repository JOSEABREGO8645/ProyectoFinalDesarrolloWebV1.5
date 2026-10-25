"""Fuentes HTML editables y evidencias sanitizadas. Solo biblioteca estándar.

Ejecutar desde cualquier carpeta: python docs/herramientas/generar_documentacion.py
Los PDF se imprimen después con generar_pdf.mjs y Microsoft Edge/CDP.
"""
from pathlib import Path
from html import escape
import json
import subprocess

DOCS = Path(__file__).resolve().parents[1]
ROOT = DOCS.parent
AUTOR = "José Alfredo Davila Abrego"
CARNE = "7490236074"
FECHA = "24 de octubre de 2026"
for carpeta in ("fuentes", "diagramas", "evidencias"):
    (DOCS / carpeta).mkdir(exist_ok=True)


def p(texto):
    return "<p>" + escape(texto) + "</p>"


def h(titulo, contenido):
    return "<h2>" + escape(titulo) + "</h2>" + contenido


def lista(elementos, numerada=False, inicio=1):
    tag = "ol" if numerada else "ul"
    return f'<{tag} start="{inicio}">' + "".join("<li>" + escape(x) + "</li>" for x in elementos) + f"</{tag}>"


def tabla(cabecera, filas):
    return '<table><thead><tr>' + ''.join('<th>' + escape(str(x)) + '</th>' for x in cabecera) + '</tr></thead><tbody>' + ''.join('<tr>' + ''.join('<td>' + escape(str(x)) + '</td>' for x in fila) + '</tr>' for fila in filas) + '</tbody></table>'


def codigo(texto):
    return '<pre>' + escape(texto.strip()) + '</pre>'


def aviso(texto):
    return '<aside>' + escape(texto) + '</aside>'


def imagen(nombre, leyenda, clase=""):
    if not (DOCS / "imagenes" / (nombre + ".png")).exists():
        raise RuntimeError("Falta ilustración: " + nombre)
    return f'<figure class="{clase}"><img src="../imagenes/{nombre}.png" alt="{escape(leyenda)}"><figcaption>{escape(leyenda)}</figcaption></figure>'


def diagrama(nombre, leyenda):
    return f'<figure class="diagrama"><img src="../diagramas/{nombre}.svg" alt="{escape(leyenda)}"><figcaption>{escape(leyenda)}</figcaption></figure>'


def portada(titulo, subtitulo):
    return '<div class="portada"><div class="marca">LSF / ERP WEB</div><p class="institucion">Universidad Mariano Gálvez<br>Centro Universitario Cuilapa, Santa Rosa<br>Ingeniería en sistemas de información y ciencias de la computación</p><h1>' + escape(titulo) + '</h1><p class="bajada">' + escape(subtitulo) + '</p><div class="autor"><strong>' + AUTOR + '</strong><br>Carné ' + CARNE + '<br>Trabajo individual</div><p>Curso: Desarrollo Web<br>Catedrático: M.A. Ing. Elvis Morales</p><p class="fecha">' + FECHA + '<br>Librería San Francisco · Stack 3</p></div>'


def flujo(nombre, titulo, pasos, nota):
    """Diagramas vectoriales propios, sin dependencias gráficas."""
    alto = 110 + len(pasos) * 100
    partes = [f'<svg xmlns="http://www.w3.org/2000/svg" width="760" height="{alto}" viewBox="0 0 760 {alto}"><defs><marker id="a" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0 0L8 4L0 8" fill="#4656ba"/></marker></defs><rect width="760" height="{alto}" rx="20" fill="#f5f3fa"/><text x="32" y="37" font-family="Arial" font-size="20" font-weight="bold" fill="#1f263e">{escape(titulo)}</text>']
    for i, (principal, detalle) in enumerate(pasos):
        y = 60 + i * 100
        if i:
            partes.append(f'<path d="M380 {y-24}V{y-5}" stroke="#4656ba" stroke-width="2" marker-end="url(#a)"/>')
        partes.append(f'<rect x="32" y="{y}" width="696" height="76" rx="12" fill="white" stroke="#c8cce9"/><circle cx="62" cy="{y+28}" r="15" fill="#4656ba"/><text x="62" y="{y+33}" text-anchor="middle" font-family="Arial" font-size="14" fill="white">{i+1}</text><text x="88" y="{y+29}" font-family="Arial" font-size="17" font-weight="bold" fill="#1f263e">{escape(principal)}</text><text x="88" y="{y+54}" font-family="Arial" font-size="14" fill="#535b71">{escape(detalle)}</text>')
    partes.append(f'<text x="32" y="{alto-20}" font-family="Arial" font-size="13" fill="#535b71">{escape(nota)}</text></svg>')
    (DOCS / "diagramas" / (nombre + ".svg")).write_text(''.join(partes), encoding='utf-8')


def red(nombre, titulo, nodos, enlaces, nota, alto=710, flechas=False):
    partes = [f'<svg xmlns="http://www.w3.org/2000/svg" width="760" height="{alto}" viewBox="0 0 760 {alto}"><defs><marker id="a" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0 0L8 4L0 8" fill="#4656ba"/></marker></defs><rect width="760" height="{alto}" rx="20" fill="#f5f3fa"/><text x="24" y="36" font-family="Arial" font-size="20" font-weight="bold" fill="#1f263e">{escape(titulo)}</text>']
    for recorrido, etiqueta, x, y in enlaces:
        final = ' marker-end="url(#a)"' if flechas else ''
        partes.append(f'<path d="{recorrido}" fill="none" stroke="#4656ba" stroke-width="2"{final}/>')
        if etiqueta:
            partes.append(f'<rect x="{x-28}" y="{y-14}" width="56" height="19" rx="4" fill="#f5f3fa"/><text x="{x}" y="{y}" text-anchor="middle" font-family="Arial" font-size="13" fill="#3343a5">{escape(etiqueta)}</text>')
    for x, y, ancho, altura, tituloNodo, lineas in nodos:
        partes.append(f'<rect x="{x}" y="{y}" width="{ancho}" height="{altura}" rx="12" fill="white" stroke="#c8cce9"/><text x="{x+14}" y="{y+27}" font-family="Arial" font-size="17" font-weight="bold" fill="#1f263e">{escape(tituloNodo)}</text>')
        for i, linea in enumerate(lineas):
            partes.append(f'<text x="{x+14}" y="{y+49+i*19}" font-family="Arial" font-size="12.5" fill="#535b71">{escape(linea)}</text>')
    partes.append(f'<text x="24" y="{alto-18}" font-family="Arial" font-size="12" fill="#535b71">{escape(nota)}</text></svg>')
    (DOCS / 'diagramas' / (nombre + '.svg')).write_text(''.join(partes), encoding='utf-8')


red('arquitectura', 'Arquitectura y límites de responsabilidad', [
    (30, 65, 300, 90, 'Vue 3 · navegador', ['Vistas, componentes y services', 'Axios; navegación por hash']),
    (430, 65, 300, 90, 'Laravel · HTTP /api', ['JWT + módulo + sucursal', 'FormRequest; Controller delgado']),
    (430, 230, 300, 95, 'Services', ['Reglas de negocio y transacciones', 'InventarioService registra el kardex']),
    (430, 420, 300, 90, 'Repositories', ['Acceso a datos exclusivamente aquí', 'Filtros, actualizaciones y agregaciones']),
    (430, 590, 300, 90, 'MongoDB · replica set', ['Documentos y referencias string', 'Saldos, sesiones e índices únicos']),
    (30, 230, 300, 95, 'Adaptadores HTTP', ['Credenciales de servicio', 'Validación de respuesta y errores']),
    (30, 420, 300, 90, 'Servicios externos', ['Banguat SOAP/HTTPS', 'Facturación y pagos de prueba']),
], [('M330 110H430', '/api', 380, 100), ('M580 155V230', 'delega', 580, 195), ('M580 325V420', 'datos', 580, 375), ('M580 510V590', 'consulta', 580, 553), ('M430 278H330', 'HTTP', 380, 268), ('M180 325V420', 'petición', 180, 375)], 'En publicación, la interfaz y /api deben conservar el mismo origen HTTPS.', alto=740, flechas=True)
red('conceptual', 'Modelo conceptual · relaciones principales', [
    (20, 70, 200, 80, 'Usuario', ['Sucursales asignadas', 'Principal + sesiones']),
    (280, 70, 200, 80, 'Sucursal', ['Ubicación del inventario', 'Origen o destino']),
    (540, 70, 200, 80, 'Producto', ['Código, nombre y precio', 'Catálogo compartido']),
    (20, 240, 200, 80, 'Cliente', ['NIT y nombre', 'Identificación en venta']),
    (280, 240, 200, 90, 'Existencia', ['Producto × sucursal', 'Una cantidad por pareja']),
    (540, 240, 200, 80, 'Proveedor', ['Abastecedor activo', 'Asociado a productos']),
    (20, 415, 200, 90, 'Venta', ['Cliente y sucursal', 'Detalle de productos']),
    (280, 415, 200, 90, 'Ingreso', ['Proveedor y sucursal', 'Detalle de productos']),
    (540, 415, 200, 90, 'Traslado', ['Origen y destino', 'Detalle de productos']),
    (280, 575, 200, 70, 'Movimiento', ['Operación, saldo y actor']),
], [('M220 110H280', 'N : N', 250, 100), ('M380 150V240', '1 : N', 410, 197), ('M540 120L480 270', '1 : N', 520, 196), ('M640 240V150', '1 : N', 670, 196), ('M120 320V415', '1 : N', 152, 370), ('M540 290L480 450', '1 : N', 520, 370), ('M120 505L280 610', '1 : N', 185, 550), ('M380 505V575', '1 : N', 410, 544), ('M640 505L480 610', '1 : N', 570, 550)], 'Referencias adicionales de productos, sucursales y actores: véase el modelo documental.')
flujo("ingreso", "Proceso 1 · abastecimiento", [
    ("Proveedor y productos activos", "El producto debe pertenecer al proveedor seleccionado."),
    ("Ingreso en la sucursal activa", "Cantidad válida y costo calculado desde el catálogo del servidor."),
    ("Transacción MongoDB", "Documento + saldo aumentado + movimiento de entrada."),
    ("Consulta y pago de prueba opcional", "Comprobante simulado independiente; no mueve dinero bancario."),
], "Responsable del diseño, implementación y demostración: José Alfredo Davila Abrego.")
flujo("venta", "Proceso 2 · venta y cobro", [
    ("Escaneo / búsqueda + cliente por NIT", "Código + Enter; cantidad*código; Consumidor Final disponible."),
    ("Detalle → efectivo → código de facturación", "Valida stock, precios actuales, total, efectivo y PIN propio."),
    ("Transacción MongoDB", "Venta emitida + salida de saldo + kardex; UUID evita duplicados."),
    ("Comprobante y reportes", "Impresión y factura de prueba opcional; documento conservado."),
], "Responsable del diseño, implementación y demostración: José Alfredo Davila Abrego.")
red('traslado', 'Proceso 3 · traslado entre sucursales', [
    (180, 65, 400, 100, 'Origen activo → enviar', ['Destino activo y distinto; stock suficiente.', 'Documento + salida de origen + kardex.']),
    (180, 230, 400, 100, 'ENVIADO · unidades en tránsito', ['Solo una de las dos acciones siguientes.', 'El usuario debe activar la sucursal correspondiente.']),
    (20, 450, 335, 110, 'ANULADO · desde origen', ['Devuelve las unidades al origen.', 'Movimiento inverso de inventario.', 'No puede confirmarse después.']),
    (405, 450, 335, 110, 'CONFIRMADO · desde destino', ['Suma las unidades al destino.', 'Movimiento de recepción.', 'No puede anularse después.']),
], [('M380 165V230', '', 0, 0), ('M380 330V385H187V450', 'anular', 187, 420), ('M380 385H572V450', 'confirmar', 572, 420)], 'Responsable: José Alfredo Davila Abrego. Los dos resultados finales son excluyentes.', alto=635, flechas=True)

# Rutas reales: leer metadatos, nunca respuestas de autenticación.
rutas = json.loads(subprocess.check_output(['php', 'artisan', 'route:list', '--path=api', '--json'], cwd=ROOT / 'backend', text=True, encoding='utf-8'))
rutas_publicas = [{k: r.get(k) for k in ('method', 'uri', 'name', 'action', 'middleware')} for r in rutas]
(DOCS / 'evidencias' / 'rutas-api.json').write_text(json.dumps(rutas_publicas, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

resultados = []
for suite in ['auth', 'catalogos', 'inventario', 'dashboard', 'reportes', 'integraciones', 'sucursales', 'caja', 'demostracion']:
    archivo = ROOT / 'frontend' / 'test-results' / (suite + '-postman.json')
    if not archivo.exists():
        raise RuntimeError('Falta resultado comprobable de Postman: ' + suite)
    run = json.loads(archivo.read_text(encoding='utf-8'))['run']
    stats = run['stats']
    resumen = {'suite': suite, 'solicitudes': stats['requests']['total'], 'aserciones': stats['assertions']['total'], 'fallos': len(run.get('failures', []))}
    if resumen['fallos']:
        raise RuntimeError('La evidencia contiene fallos: ' + suite)
    resultados.append(resumen)
evidencia = {'fecha': '2026-10-24', 'alcance': 'Resultados locales ejecutados en la versión documentada; no son pruebas en la nube.', 'backend': {'pruebas': 193, 'aserciones': 1538, 'fuente': 'MEMORY.md, preparación de publicación y caché persistente'}, 'postman': resultados, 'rutas_api': len(rutas_publicas), 'privacidad': 'Solo conteos. Se excluyen entornos, cookies, tokens, claves y cuerpos de solicitudes.'}
(DOCS / 'evidencias' / 'resultados-verificados.json').write_text(json.dumps(evidencia, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

REGLAS = [
    'Toda operación de escritura exige sesión válida y permiso del módulo en el backend.',
    'Solo el superusuario administra usuarios; el empleado recibe módulos explícitos.',
    'El usuario solo puede alternar entre sucursales activas a las que tiene acceso.',
    'Cada sesión conserva su sucursal activa; cambiar una sesión no cambia las otras.',
    'Una escritura con contexto de sucursal anterior se rechaza y no se repite en otra sucursal.',
    'Las nuevas operaciones se registran en la sucursal activa, también para administradores.',
    'Cada producto tiene un código único; los ceros iniciales forman parte del código.',
    'Los precios y costos admiten dos decimales; los totales se calculan en el servidor.',
    'El saldo pertenece a la pareja producto–sucursal y no puede ser negativo.',
    'Toda entrada, salida o reversión de inventario deja un movimiento de kardex.',
    'Documento, saldo y movimientos se confirman juntos dentro de una transacción.',
    'Cada línea admite cantidades enteras de 1 a 100,000; máximo 100 productos distintos.',
    'Cada UUID identifica una operación; repetirla con el mismo contenido no la duplica.',
    'Reutilizar un UUID con contenido distinto se rechaza como conflicto.',
    'El ingreso requiere proveedor activo y productos activos asociados a ese proveedor.',
    'Registrar un ingreso aumenta solo el inventario de su sucursal.',
    'La venta usa precios vigentes del catálogo y conserva una instantánea del detalle.',
    'La venta exige stock suficiente en la sucursal activa y descuenta solo ese saldo.',
    'Si cambia el total esperado antes de facturar, el cobro debe revisarse.',
    'El efectivo debe cubrir el total; el cambio es efectivo menos total.',
    'Facturar requiere el código personal de cuatro dígitos; no sustituye la contraseña.',
    'El total máximo permitido de la operación es Q 99,999,999.99.',
    'Puede venderse a Consumidor Final; los NIT se normalizan antes de buscar o guardar.',
    'Un traslado requiere origen y destino activos y diferentes.',
    'Enviar un traslado descuenta el origen; el destino aún no recibe existencias.',
    'Solo la sucursal destino activa puede confirmar un traslado enviado.',
    'Solo la sucursal origen activa puede anular un traslado enviado.',
    'Confirmar suma al destino; anular devuelve al origen. Son estados terminales alternativos.',
    'Las anulaciones de ventas e ingresos corresponden al superusuario y conservan el documento.',
    'La reversión de un ingreso no puede dejar inventario negativo.',
    'Las referencias usadas impiden eliminar entidades necesarias para el historial.',
    'Los reportes del empleado se restringen a su sucursal; el administrador puede consultar todas.',
    'El servicio de facturación y el de pagos son simulaciones, sin validez fiscal ni movimiento bancario.',
    'Los servicios externos reciben datos mínimos; nunca contraseña, JWT personal ni PIN.',
    'Una falla de Banguat no produce una tasa inventada ni cambia la moneda GTQ del documento.',
    'Una respuesta incierta de venta se recupera con el mismo UUID antes de iniciar otra facturación.',
]

REPORTES = [
    ('Existencias', 'Saldo actual por producto; filtro de producto, categoría y proveedor.'),
    ('Valoración del inventario', 'Costo, venta y margen potencial por categoría; precios vigentes.'),
    ('Kardex', 'Entradas, salidas, saldos y responsable; período y producto.'),
    ('Ventas por día', 'Documentos, unidades, importe y ticket promedio; excluye anuladas.'),
    ('Productos más vendidos', 'Ranking de unidades e importe; período y producto.'),
    ('Ventas por cliente', 'Frecuencia, unidades e importe, incluyendo Consumidor Final.'),
    ('Ingresos por proveedor', 'Documentos, unidades y costo recibido; excluye anulados.'),
    ('Flujo de traslados', 'Origen, destino, estado y unidades del detalle.'),
    ('Auditoría de anulaciones', 'Fecha de anulación, documento, responsable, motivo e importe original.'),
]

MODULOS = [
    ('Proveedores', 'Catálogo de abastecedores; estado y referencias de productos/ingresos.'),
    ('Catálogo', 'Productos, códigos, precios, costos, mínimos y consulta de existencias.'),
    ('Clientes', 'Nombre, NIT y contactos; asociación del cliente a la venta.'),
    ('Ingresos', 'Recepción por proveedor; aumento de stock; historial y reversión autorizada.'),
    ('Ventas', 'Caja abierta, lector, cliente, cobro, código, comprobante e historial.'),
    ('Traslados', 'Envío, recepción en destino, anulación en origen y trazabilidad.'),
    ('Sucursales', 'Sucursales activas, datos y protección de referencias.'),
    ('Reportes', 'Nueve consultas con filtros, impresión y exportación CSV.'),
]

documentos = []


def guardar(nombre, titulo, paginas, horizontal=False):
    orientacion = 'landscape' if horizontal else 'portrait'
    html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="author" content="' + AUTOR + '"><title>' + escape(titulo) + '</title><link rel="stylesheet" href="../documentos.css"><style>@page { size: A4 ' + orientacion + '; }</style></head><body class="' + ('diapositivas' if horizontal else '') + '">' + ''.join('<section class="hoja">' + pagina + '</section>' for pagina in paginas) + '</body></html>'
    (DOCS / 'fuentes' / (nombre + '.html')).write_text(html, encoding='utf-8')
    documentos.append({'nombre': nombre, 'titulo': titulo, 'fuente': 'fuentes/' + nombre + '.html', 'pdf': nombre + '.pdf', 'secciones': len(paginas), 'horizontal': horizontal})


guardar('01-Proyecto', 'Documento del proyecto', [
    portada('Librería San Francisco', 'Documento del proyecto · ERP de inventario, ventas y traslados'),
    h('1. Negocio y problema', p('Librería San Francisco necesita administrar productos y operaciones en varias sucursales. Un catálogo compartido no basta para conocer qué unidades pueden venderse en cada ubicación. La captura manual, las ventas simultáneas y la recepción informal de traslados pueden causar saldos incorrectos y pérdida de trazabilidad.') + p('El sistema centraliza catálogos, controla existencias por sucursal y conserva documentos y movimientos. La caja facilita el uso de lectores de códigos de barras que envían el código seguido de Enter. Los usuarios rotativos pueden cambiar de sucursal sin compartir cuentas.'))
    + h('2. Objetivo general', p('Construir y verificar un ERP web que integre abastecimiento, venta y traslado, con seguridad por usuario, módulo y sucursal, y evidencia auditable de cada cambio de inventario.'))
    + h('3. Objetivos específicos', lista(['Mantener los catálogos y separar inventarios por ubicación.', 'Registrar ingresos, ventas y traslados con reglas transaccionales.', 'Ofrecer nueve reportes e indicadores consultables por permisos.', 'Integrar tres servicios externos y tratar sus respuestas y fallos.', 'Preparar una demostración ficticia, documentación y publicación verificable.'])),
    h('4. Alcance funcional', tabla(['Módulo', 'Función comprobada'], MODULOS) + p('Los seis primeros constituyen el núcleo de negocio. Inicio, autenticación, Usuarios y Dashboard son componentes transversales y no se usan para reemplazar los seis módulos exigidos.'))
    + h('5. Roles', tabla(['Rol', 'Alcance'], [('Superusuario', 'Administración, usuarios y operaciones autorizadas; contexto activo obligatorio al escribir.'), ('Empleado', 'Módulos asignados y acceso a sucursales autorizadas; operaciones limitadas por contexto.')]))
    + aviso('Las facturas y pagos externos son académicos y simulados. No existe integración FEL/SAT ni ejecución de transferencias bancarias reales.'),
    h('6. Reglas de negocio · 1–18', lista(REGLAS[:18], True)),
    h('6. Reglas de negocio · 19–36', lista(REGLAS[18:], True, 19)),
    h('7. Responsabilidad individual', p('Autor y responsable: ' + AUTOR + ', carné ' + CARNE + '. El estudiante informa que no se integró a un grupo. Esta matriz concentra las responsabilidades en el autor del proyecto.') + aviso('El enunciado plantea equipos de tres estudiantes y distribución individual de módulos, procesos, reportes y APIs. Se registra la modalidad individual para consideración del docente.'))
    + tabla(['Área', 'Responsable'], [(m, AUTOR) for m, _ in MODULOS])
    + p('Autenticación/JWT, Usuarios, permisos, multiasignación de sucursales, Dashboard, pruebas, Docker, Git, documentación y preparación de publicación: ' + AUTOR + '. El uso de asistencia de IA queda documentado en AGENTS.md, MEMORY.md y las instrucciones del repositorio.'),
    h('8. Procesos y APIs', tabla(['Proceso integrado', 'Módulos', 'Responsable'], [('Abastecimiento', 'Proveedores → Catálogo → Ingresos → inventario/kardex', AUTOR), ('Venta y cobro', 'Clientes + Catálogo → Ventas → inventario/kardex', AUTOR), ('Traslado', 'Sucursales + Catálogo → Traslados → recepción/kardex', AUTOR)]) + tabla(['Integración HTTP', 'Naturaleza', 'Responsable'], [('Banguat · TipoCambioDia', 'Servicio público SOAP/HTTPS', AUTOR), ('Facturación de prueba', 'API HTTP independiente simulada', AUTOR), ('Comprobante de pago', 'Otra API HTTP independiente simulada', AUTOR)]))
    + h('9. Matriz de reportes', tabla(['Reporte', 'Responsable'], [(t, AUTOR) for t, _ in REPORTES])),
    h('10. Entregables y límites actuales', tabla(['Requisito', 'Evidencia / estado'], [('Documento, reglas y responsables', 'Este PDF y su fuente HTML editable.'), ('Diseño y procesos', '02-Diseno.pdf; diagramas SVG y diccionario de datos.'), ('Manual técnico / usuario ilustrado', '03-Manual-tecnico.pdf y 04-Manual-usuario.pdf.'), ('Pruebas y presentación', '05-Evidencias.pdf y 06-Presentacion.pdf.'), ('Postman', 'docs/postman/; nueve colecciones, incluida demostración.'), ('Datos ficticios y Docker', 'libreria:demo; docker/demostracion.py; docker-compose.yml.'), ('Repositorio', 'GitHub: JOSEABREGO8645/ProyectoFinalDesarrolloWebV1.5.'), ('Publicación y prueba en línea', 'Pendientes de cuentas gratuitas, despliegue y validación real.'), ('Historial de tres integrantes', 'Modalidad individual informada; consideración docente pendiente.')]))
    + p('El documento original no establece una extensión obligatoria para los manuales o el documento del proyecto. Se entregan PDF legibles y fuentes editables en docs. Referencia de requisitos: “Proyecto final - Desarrollio Web.docx”, facilitado por el estudiante.')
    + p('La comprobación local no se presenta como una demostración en la nube. Las URLs y accesos de la entrega pública se completarán solo después de desplegar y probar. Las credenciales se entregan al docente por el medio privado que indique.'),
])

colecciones = [
    ('usuarios', 'usuario, password hash, codigo_hash, rol, modulos, sucursal_id, sucursal_ids, activo, auth_version'),
    ('auth_sessions', 'usuario_id, sucursal_id activa, token de renovación protegido, expiración y revocación'),
    ('sucursals', 'Nombre físico heredado del modelo Sucursal; nombre, dirección, teléfono y estado'),
    ('proveedores / clientes', 'Identificación, contactos y estado; cliente con NIT normalizado'),
    ('productos', 'Código, nombre, categoría, proveedor_id, costo/venta Decimal128 y mínimo'),
    ('existencias', 'producto_id + sucursal_id; cantidad del inventario de esa ubicación'),
    ('movimientos', 'producto/sucursal/operación, cantidad, antes, después, fecha y actor'),
    ('ingresos / ventas', 'Sucursal, proveedor/cliente, UUID, correlativo, estado y detalle embebido'),
    ('traslados', 'Origen, destino, UUID, estado, detalle y actores de envío/recepción/anulación'),
    ('contadores', 'Correlativos por sucursal/tipo y marca de preparación de demostración'),
    ('cache / cache_locks', 'Solo al habilitar caché MongoDB: valores, límites de intentos y bloqueos con vencimiento'),
]
guardar('02-Diseno', 'Diseño del sistema', [
    portada('Diseño del sistema', 'Modelo conceptual, MongoDB, arquitectura y procesos integrados'),
    h('1. Modelo conceptual', diagrama('conceptual', 'Relaciones conceptuales y alcance del inventario.') + p('Proveedor tiene muchos productos. Un producto puede aparecer en muchos detalles de ingreso, venta o traslado. Cliente tiene muchas ventas; Consumidor Final es una opción del flujo. Sucursal participa como ubicación de stock y como origen/destino. Usuario tiene muchas sesiones y varias sucursales disponibles.')),
    h('2. Modelo documental', tabla(['Colección física', 'Contenido principal'], colecciones) + aviso('La colección física de Sucursal es sucursals, por la convención histórica del modelo. No renombrarla a sucursales sin migración y verificación.'))
    + h('Referencias e importes', p('Los documentos tienen _id de MongoDB; las referencias entre colecciones se guardan como string. Los detalles se embeben para conservar nombres, códigos y precios originales aunque cambie el catálogo. Los precios se representan con Decimal128 y los cálculos operativos con centavos enteros.')),
    h('3. Integridad y concurrencia', lista(['Índice único de existencias por producto_id y sucursal_id.', 'Índice único del identificador UUID de operación y de correlativo por tipo/sucursal.', 'Actualización condicional del saldo: dos ventas simultáneas no pueden consumir las mismas últimas unidades.', 'InventarioService registra el cambio y su movimiento dentro de la transacción del proceso.', 'OperacionService coordina documentos y anulaciones; los controladores no consultan modelos.', 'La idempotencia compara la huella de la petición y recupera el documento existente.', 'Los servicios externos usan una reserva temporal y una clave estable por documento.']))
    + h('Organización del código', codigo('backend/app/Models → Repositories → Services → Http/Controllers\nbackend/app/Http/Requests · middleware de autenticación y módulo\nfrontend/src/views · components/<modulo> · services\nfrontend/src/style.css · navegación por hash\ndatabase/COLECCIONES_MONGODB.md · docs/postman/'))
    + p('El diccionario de colecciones y los índices concretos se conservan en database/COLECCIONES_MONGODB.md y las migraciones MongoDB del repositorio. No se usa una migración SQL para estas colecciones.'),
    h('4. Arquitectura', diagrama('arquitectura', 'Capas del ERP y adaptadores de servicios externos.')),
    h('5. Proceso de abastecimiento', diagrama('ingreso', 'Flujo integrado 1: proveedor, producto, ingreso e inventario.') + p('Validaciones: proveedor y productos activos, asociación correcta, cantidades válidas y sucursal autorizada. Resultado: ingreso registrado, incremento del saldo y movimiento auditable. Si falla una validación o la transacción, no queda una entrada parcial.')),
    h('6. Proceso de venta y cobro', diagrama('venta', 'Flujo integrado 2: cliente, producto, caja y salida de inventario.') + p('Validaciones: permisos, sucursal, stock, precios actuales, total esperado, efectivo y código personal. La interfaz retiene el UUID ante una respuesta incierta y permite recuperar la venta sin registrar otra. El borrador es de memoria: cerrar o recargar la pestaña lo pierde.')),
    h('7. Proceso de traslado', diagrama('traslado', 'Flujo integrado 3: envío y recepción separados por sucursal.') + p('Estado inicial enviado. Confirmado y anulado son finales excluyentes. Un administrador también debe activar el destino para confirmar o el origen para anular. El mismo usuario puede realizar ambas partes alternando entre sucursales autorizadas, mientras el traslado siga enviado.')),
])

guardar('03-Manual-tecnico', 'Manual técnico', [
    portada('Manual técnico', 'Instalación, configuración, operación, pruebas y preparación de publicación'),
    h('1. Requisitos', tabla(['Herramienta', 'Versión / función'], [('PHP', 'Entorno verificado: 8.5.10. Composer lock requiere PHP ≥8.4.1.'), ('Composer', 'Instalar las versiones fijadas en backend/composer.lock.'), ('Extensiones PHP', 'mongodb, curl, openssl, mbstring, DOM/XML y las exigidas por Composer.'), ('Laravel', '13; integración mongodb/laravel-mongodb 5.11; JWT con firebase/php-jwt.'), ('Node.js / npm', 'Entorno verificado Node 24.19; usar frontend/package-lock.json.'), ('Vue / Vite', 'Vue 3; Vite 8; Axios. Sin biblioteca de componentes visuales.'), ('Docker Desktop / Compose', 'MongoDB con replica set de un nodo para transacciones.'), ('Navegador', 'Edge/Chrome actualizado; se probó escritorio y ancho móvil.')]))
    + p('No ejecute composer update ni npm update para instalar una copia reproducible. Use install/ci y los archivos lock. En PowerShell puede usarse npm.cmd para evitar el bloqueo de npm.ps1.')
    + codigo('git clone https://github.com/JOSEABREGO8645/ProyectoFinalDesarrolloWebV1.5.git\ncd ProyectoFinalDesarrolloWebV1.5\ncd backend\ncomposer install\ncd ../frontend\nnpm.cmd ci'),
    h('2. Configuración inicial', p('Solo en una instalación nueva, copie backend/.env.example a backend/.env y frontend/.env.example a frontend/.env. No sobrescriba los archivos de una instalación existente. Complete DB_URI con la autenticación de su MongoDB local y mantenga DB_DATABASE=libreria_san_francisco para el sistema habitual. Las claves reales no se guardan en Git.'))
    + codigo('cd backend\nphp artisan key:generate\nphp artisan libreria:jwt-clave\nphp artisan config:clear\ncd ..\ndocker compose up -d --wait')
    + p('El contenedor local se llama mongodb_libreria y expone MongoDB en el puerto 27018. Los scripts docker/mongo/iniciar.sh y salud.sh preparan el replica set rs0 y su autenticación interna. Espere un estado saludable. Un MongoDB standalone no permite las transacciones necesarias para las operaciones.')
    + h('Índices', codigo('cd backend\nphp artisan migrate --database=mongodb --path=database/migrations/2026_10_23_232450_crear_indices_inventario.php --path=database/migrations/2026_10_24_054617_agregar_indices_dashboard.php'))
    + aviso('El docker-compose.yml local no debe exponerse directamente a Internet. La publicación requiere credenciales nuevas y reglas de red propias; no reutilice accesos locales.'),
    h('3. Servidores y primer usuario', codigo('cd backend\nphp artisan libreria:crear-admin --generar-clave\nphp artisan serve\n\n# Otra terminal, desde la raíz\ncd frontend\nnpm.cmd run dev'))
    + p('El alta inicial usa una sucursal activa existente. --sucursal permite indicar su ID; --crear-sucursal se reserva para una base vacía. No hay registro público. El comando conserva usuarios existentes. El acceso inicial local se guarda de forma privada en backend/storage/app/private/acceso-inicial.txt; no lo agregue al repositorio ni a los PDF.')
    + tabla(['Servicio habitual', 'Dirección'], [('Interfaz', 'http://localhost:5173'), ('API', 'http://127.0.0.1:8000/api'), ('MongoDB local', 'localhost:27018, autenticado')])
    + p('VITE_API_URL=/api y VITE_API_PROXY_TARGET apuntan el proxy de Vite al backend. FRONTEND_ORIGINS contiene los orígenes de la interfaz, con protocolo y puerto. Después de cambiar variables del frontend, reinicie Vite; después de cambiar el backend, limpie su configuración.'),
    h('4. Sesiones y seguridad', p('El access token JWT dura 15 minutos por defecto y permanece en memoria del navegador. La renovación usa una cookie HttpOnly rotativa con límite absoluto de siete días. No se guardan JWT ni PIN en localStorage o sessionStorage. Cerrar sesión revoca la sesión y limpia el estado de la interfaz.'))
    + tabla(['Control', 'Implementación'], [('Autenticación', 'Firma, propósito, emisor, audiencia, fechas y sesión activa.'), ('Autorización', 'Middleware modulo; denegación predeterminada. Usuarios exclusivo del superusuario.'), ('Renovación', 'Cookie libreria_refresh; ruta /api/auth; SameSite=Lax; Secure en producción.'), ('Sucursales', 'POST /api/auth/sucursal; X-Sucursal-Id evita escrituras en contexto obsoleto.'), ('Contraseñas y código', 'Hashes; el código no se devuelve en listados ni se envía a las APIs externas.'), ('Cambios de acceso', 'Cambiar asignaciones y permisos revoca sesiones según las reglas del servicio.')])
    + p('En publicación conviene servir la interfaz y /api bajo el mismo origen HTTPS. No basta permitir CORS entre dos dominios ajenos: la cookie SameSite debe poder renovarse correctamente. APP_DEBUG=false y JWT_COOKIE_SECURE=true son obligatorios en ese entorno.'),
    h('5. Variables de entorno', tabla(['Variable', 'Uso'], [('APP_ENV / APP_DEBUG / APP_URL', 'Entorno, errores detallados y URL pública real del backend.'), ('APP_KEY / JWT_SECRET', 'Claves distintas del framework y de JWT; generar por instalación.'), ('DB_CONNECTION / DB_URI / DB_DATABASE', 'mongodb, URI privada y nombre de base.'), ('FRONTEND_ORIGINS', 'Orígenes autorizados, separados por comas.'), ('JWT_COOKIE_NAME / JWT_COOKIE_SECURE', 'Cookie aislada por instancia y HTTPS en producción.'), ('VITE_API_URL / VITE_API_PROXY_TARGET', 'Ruta pública /api y destino local del proxy de desarrollo.'), ('VITE_DEMO', 'Bandera visual de datos ficticios, fijada al compilar.'), ('FACTURACION_API_URL / TOKEN', 'URL y credencial del proveedor independiente de facturas de prueba.'), ('PAGOS_API_URL / TOKEN', 'URL y credencial del proveedor independiente de pagos de prueba.'), ('BANGUAT_API_URL / BANGUAT_CA_BUNDLE', 'Servicio SOAP oficial y CA opcional confiable; TLS permanece verificado.')]))
    + p('Los archivos .env, storage/app/private y frontend/test-results están excluidos de Git. El documento de evidencias exporta únicamente conteos y metadatos de rutas; los reportes crudos de Newman pueden contener accesos y no deben publicarse.'),
    h('6. API del ERP', tabla(['Grupo', 'Operaciones'], [('Autenticación', 'POST auth/login, auth/refresh, auth/logout, auth/sucursal; GET auth/me.'), ('Catálogos CRUD', 'Sucursales, proveedores, clientes, productos y usuarios, protegidos por módulo.'), ('Selectores', 'GET catalogos/*: DTO mínimos para operaciones, sin exigir permiso de edición.'), ('Operaciones', 'GET/POST ingresos, ventas, traslados; GET por ID; acciones de anular/confirmar.'), ('Inventario', 'GET inventario/movimientos y consultas de existencias.'), ('Dashboard', 'GET dashboard; fecha y sucursal según permisos.'), ('Reportes', 'Metadatos, JSON paginado y CSV de los nueve tipos.'), ('Integraciones', 'Tipo de cambio, factura de prueba y comprobante de pago.')]))
    + p(f'El inventario completo de {len(rutas_publicas)} rutas reales está en docs/evidencias/rutas-api.json. Importe las colecciones docs/postman/*.postman_collection.json. Configure la URL local y credenciales privadas en un entorno propio de Postman; no publique ese entorno.')
    + h('Estados de respuesta', tabla(['HTTP', 'Interpretación'], [('401 / 403', 'Sesión inválida / permiso insuficiente.'), ('404', 'ID inexistente o documento no accesible para ese usuario.'), ('409', 'Conflicto de estado, idempotencia, precio o sucursal cambiada.'), ('422 / 429', 'Validación o regla de negocio / límite de intentos.'), ('502 / 503', 'Respuesta externa incoherente / servicio no disponible.')])) ,
    h('7. Integraciones externas', p('Responsable de las tres integraciones: ' + AUTOR + '. Las APIs simuladas se ejecutan en procesos HTTP distintos del backend principal y se consultan realmente. Banguat es el servicio público real; los otros dos son proveedores académicos de prueba permitidos por el enunciado.'))
    + tabla(['Proveedor', 'Contrato y tratamiento'], [('Banguat', 'SOAP 1.1 TipoCambioDia sobre HTTPS; fecha/referencia verificadas, XML sin entidades externas y caché de 30 minutos.'), ('Facturación', 'POST /v1/facturas en 127.0.0.1:8101; Bearer de servicio e Idempotency-Key; respuesta FAC-DEMO, SIN_VALOR_FISCAL.'), ('Pagos', 'POST /v1/comprobantes-pago en 127.0.0.1:8102; otro Bearer de servicio; PAGO-DEMO, SIN_MOVIMIENTO_BANCARIO.')])
    + codigo('cd backend\nphp artisan libreria:apis-demo --no-interaction\ncd ..\nnode docker/apis/iniciar.mjs')
    + p('Conserve el archivo privado de configuración y el historial JSON de ambos proveedores. Su clave de idempotencia permite recuperar el mismo comprobante tras reiniciar con ese historial. Se rechaza una clave usada con otro contenido. No hay reintentos automáticos de los POST. La respuesta se valida antes de guardarla en el ERP.')
    + p('Si Windows confía en Banguat pero PHP informa un error de CA, configure un PEM apropiado siguiendo la sección HTTPS de Banguat del README. Nunca desactive la comprobación TLS. El servidor público debe usar su propio almacén de CA, no el archivo privado de esta máquina.'),
    h('8. Datos de demostración', codigo('python docker/demostracion.py\n# Verificación automática, sin dejar servidores de esta ejecución abiertos:\npython docker/demostracion.py --verificar'))
    + p('La demostración usa libreria_demo, interfaz 127.0.0.1:5176 y API 127.0.0.1:8003. Tiene JWT, cookie y caché separados del sistema habitual. El comando no borra ni sustituye la base real; conserva la demostración y sus accesos al repetirlo.')
    + tabla(['Usuario ficticio', 'Perfil inicial'], [('admin.demo', 'Superusuario; administra y demuestra todo el sistema.'), ('caja.demo', 'Ventas y Clientes en DEMO Central.'), ('bodega.demo', 'Catálogo, Ingresos y Traslados en DEMO Central.'), ('reportes.demo', 'Reportes de DEMO Central.'), ('destino.demo', 'Traslados en DEMO Norte.')])
    + p('Las contraseñas y códigos aleatorios están únicamente en backend/storage/app/private/demo/libreria_demo/accesos.json. No se incluyen en los manuales. El conjunto inicial contiene cinco sucursales, cinco proveedores, cinco clientes, diez productos y operaciones que alimentan los nueve reportes. Puede cambiar al operar la demostración; las cantidades iniciales no son un inventario actual garantizado.'),
    h('9. Pruebas y mantenimiento', codigo('cd backend\nphp artisan route:list --path=api\nphp artisan test --compact\nphp vendor/bin/pint --dirty --format agent\ncd ../frontend\nnpm.cmd run build\nnode --test tests/caja.test.mjs\ncd ..\nnode --test docker/apis/simulador.test.mjs'))
    + p('frontend/tests/run-browser.py prepara una base temporal propia y ejecuta las suites de navegador y Postman. Requiere Edge/CDP y las dependencias de pruebas ya preparadas. Use sus opciones --help y las instrucciones de .ia/TESTING.md. No apunte pruebas destructivas a la base real ni cambie sus guardas de nombre/entorno.')
    + h('Respaldo y recuperación', lista(['Antes de migrar o trasladar datos, cree un mongodump autenticado en una ubicación privada.', 'Conserve el archivo junto con la fecha y el nombre de base; no lo agregue a Git.', 'Restaure primero en otra base o entorno y compruebe saldos, documentos y kardex.', 'Almacene los secretos y el historial de las APIs simuladas con respaldo propio.', 'No use docker compose down -v para resolver un fallo: elimina los volúmenes de datos.']))
    + p('El contenedor puede ejecutar mongodump/mongorestore con las variables de autenticación internas, sin escribir claves literales en la línea de comandos. Los comandos concretos de copia y restauración están en database/RESPALDO.md y deben adaptarse a la base de destino.'),
    h('10. Publicación gratuita · preparación', p('Se preparó la configuración de Render para la aplicación y MongoDB Atlas Free para la base. Las cuentas y los recursos todavía no existen. Deben crearse y comprobarse antes de completar esta fase. La interfaz y /api comparten origen en la imagen Docker; las dos APIs simuladas se publican como servicios independientes.'))
    + aviso('Para publicar: CACHE_STORE=mongodb en Laravel; API_MONGO_URI y API_MONGO_DATABASE en cada proveedor. Se verificaron caché, bloqueos y límites persistentes, e idempotencia de los dos proveedores entre procesos y reinicios con MongoDB local. Eso no sustituye la prueba en Atlas.')
    + lista(['Crear las cuentas gratuitas y un clúster Atlas Free con usuario de aplicación y acceso de red limitado.', 'Preparar imágenes, secretos y almacenamiento persistente compatible con el plan elegido.', 'Cargar únicamente datos ficticios de demostración, separados de la base comercial local.', 'Desplegar la interfaz, API y proveedores; configurar URLs reales y orígenes.', 'Probar inicio, renovación, permisos, sucursales, procesos completos y las tres integraciones en HTTPS.', 'Registrar URLs, fecha, evidencia de conexión y pruebas; entregar accesos por canal privado.'], True)
    + p('Procedimiento y variables: docs/PUBLICACION.md. Incluye los dos Blueprints y el preparador privado de Atlas. Render Free usa disco efímero, suspende servicios inactivos y comparte 750 horas mensuales por workspace; Atlas Free limita almacenamiento a 0.5 GB. Revisar los planes antes de aceptar recursos de pago.')
    + '<p class="small">Fuentes: <a href="https://render.com/docs/free">Render · Deploy for Free</a>; <a href="https://www.mongodb.com/docs/atlas/reference/free-shared-limitations/">MongoDB · Atlas Free Cluster Limits</a>. Revisión: 24/10/2026.</p>',
])

guardar('04-Manual-usuario', 'Manual de usuario ilustrado', [
    portada('Manual de usuario', 'Guía ilustrada para administración, caja, bodega y consulta de reportes'),
    h('1. Iniciar y cerrar sesión', lista(['Abra la URL que indique el administrador. En demostración local: http://127.0.0.1:5176.', 'Escriba su usuario y contraseña. Pulse Iniciar sesión.', 'Verá Inicio con el mensaje de bienvenida y su sucursal activa.', 'Use el icono Cerrar sesión al terminar, especialmente en equipos compartidos.'], True) + imagen('acceso', 'Pantalla de acceso sin credenciales. Todas las capturas proceden de la demostración ficticia.') + p('Si el acceso es rechazado, revise las credenciales y el estado de la cuenta. Después de varios intentos debe esperar. Solicite al administrador restablecer el acceso; el código de facturación no es la contraseña.')),
    h('2. Inicio y navegación', imagen('inicio', 'Bienvenida, usuario activo y menú lateral. Ventas aparece inmediatamente debajo de Inicio.') + p('La barra muestra los módulos permitidos. No ver un módulo puede deberse a su perfil; no significa que sus datos hayan desaparecido. Inicio siempre es la primera pantalla después de iniciar sesión. El botón de casa permite volver.')
    + h('Cambiar de sucursal', p('Use el selector debajo de su nombre, arriba a la derecha. Solo aparecen sucursales activas disponibles para su cuenta. Al elegir otra se recargan los datos y el inventario de esa ubicación. Compruebe el nombre antes de cobrar, recibir mercadería o enviar/aceptar traslados. El cambio se bloquea mientras se guarda una operación.'))
    + aviso('El borrador de venta se separa por usuario y sucursal mientras la pestaña sigue abierta. Recargar o cerrar el navegador pierde los borradores sin facturar.')),
    h('3. Usuarios y sucursales asignadas', imagen('usuarios', 'Administración de cuentas: disponible exclusivamente para el superusuario.') + lista(['Entre en Usuarios y pulse Agregar usuario.', 'Complete nombre, usuario y contraseña; asigne el código de facturación de cuatro dígitos.', 'Elija rol, módulos permitidos, sucursal principal y las sucursales adicionales de trabajo.', 'Guarde y entregue el acceso de forma privada. Nunca comparta el código entre cajeros.', 'Para modificar el acceso, abra Editar. Los cambios de permisos/asignaciones pueden exigir iniciar sesión nuevamente.'], True)
    + p('Una cuenta empleada puede trabajar en varias sucursales autorizadas. Un superusuario dispone de las sucursales activas, pero también debe activar la ubicación correspondiente para operar. Las sucursales con usuarios o documentos relacionados no se eliminan libremente.')),
    h('4. Productos, proveedores y clientes', imagen('catalogo', 'Catálogo común con existencias de la sucursal seleccionada.') + lista(['Registre primero el proveedor si recibirá productos asociados a él.', 'En Catálogo, use Agregar producto y complete código, nombre, costo, precio, mínimo y proveedor.', 'Conserve los ceros iniciales del código. Puede buscar por código, nombre o categoría.', 'En Clientes, guarde nombre y NIT. El sistema normaliza el NIT al consultar y guardar.', 'Los filtros de existencias cambian la consulta; no trasladan unidades ni modifican la sucursal activa.'], True)
    + aviso('Editar el producto no agrega stock. Use Ingresos para recibir mercadería y Traslados para moverla entre sucursales. La eliminación de registros referenciados puede ser rechazada para conservar el historial.')),
    h('5. Registrar un ingreso', imagen('ingresos', 'Historial de ingresos de la demostración.') + lista(['Confirme la sucursal activa y entre en Ingresos.', 'Abra un nuevo ingreso y seleccione un proveedor activo.', 'Agregue productos de ese proveedor y sus cantidades.', 'Revise el detalle y guarde. El sistema calcula el costo y aumenta el inventario de esta sucursal.', 'Abra el documento para consultar o imprimir. Si corresponde, genere el comprobante de pago de prueba.'], True)
    + p('El pago de prueba no ejecuta una transferencia bancaria. Para corregir un ingreso ya registrado, un superusuario puede anularlo con motivo, siempre que exista stock para revertirlo. No borre el documento para cuadrar existencias.')),
    h('6. Caja de ventas · agregar productos', imagen('venta-detalle', 'Detalle agregado con 2*000-DEMO-001. Los códigos y cantidades son ejemplos ficticios.') + lista(['Entre en Ventas: la caja y el buscador ya están abiertos.', 'Escanee el código y envíe Enter para una unidad.', 'Para varias unidades, escriba cantidad*código y pulse Enter. Ejemplo: 5*000-DEMO-001.', 'Para buscar por nombre, escriba parte del nombre y seleccione el producto.', 'Revise o ajuste cantidades y quite líneas equivocadas antes de cobrar.'], True)
    + p('Un lector externo normalmente envía el código y Enter automáticamente. Se verificó ese comportamiento mediante teclado simulado; no se afirma haber probado un escáner físico. Si no existe el código o falta inventario, aparecerá un aviso y la línea no se agrega.')),
    h('7. Cliente y revisión antes de cobrar', imagen('ventas', 'Buscador de productos y botón Agregar cliente (NIT) junto a él.') + lista(['Pulse Agregar cliente (NIT). Busque el NIT exacto o seleccione un cliente por nombre.', 'Si no existe y tiene permiso de Clientes, use Registrar nuevo cliente.', 'Si la compra no requiere cliente identificado, conserve Consumidor Final · CF.', 'Revise cantidades, cliente y total. Pulse Guardar (facturar).', 'Si cambiaron precios o existencias, revise el aviso y el detalle actualizado antes de seguir.'], True)
    + p('Guardar (facturar) abre el cobro; la venta todavía no queda registrada. Los precios finales se verifican nuevamente en el servidor.')),
    h('8. Cobrar y autorizar', imagen('venta-cobro', 'Paso 1: total, efectivo y cambio calculado.', 'dialogo') + lista(['Ingrese el efectivo recibido. Debe cubrir el total.', 'Revise el cambio y pulse Confirmar cobro.', 'En Autorizar venta, ingrese su código personal de cuatro dígitos y pulse Facturar venta.'], True) + imagen('venta-autorizacion', 'Paso 2: código personal sin completar. El código no se publica en el manual.', 'dialogo')
    + p('Después del registro se abre el comprobante y la caja queda lista para la siguiente venta. Puede imprimirlo o solicitar la factura de prueba. Esa factura no tiene validez fiscal.')),
    h('9. Traslados entre sucursales', imagen('traslados', 'Consulta de traslados enviados, confirmados y anulados con datos ficticios.') + lista(['Active la sucursal de origen y cree un traslado hacia una sucursal distinta.', 'Agregue productos disponibles y envíe. Las unidades salen del origen y quedan en tránsito.', 'Para recibir, active la sucursal destino con una cuenta autorizada, abra el traslado y confirme.', 'Para cancelarlo mientras sigue enviado, active la sucursal de origen y anule con un motivo.'], True)
    + aviso('Origen puede anular y destino puede confirmar. El administrador respeta la misma regla. Un traslado ya confirmado o anulado no vuelve a ejecutarse. Verlo en el historial no concede permiso para ambas acciones.')),
    h('10. Dashboard', imagen('dashboard', 'Indicadores calculados a partir de las operaciones registradas.') + p('Seleccione sucursal y período para consultar ventas, ingresos, gráfica diaria y documentos recientes. Los indicadores de inventario son actuales; los movimientos y documentos usan el período elegido. Los traslados pendientes pueden haberse enviado antes de ese período y seguir en tránsito.')
    + p('Puede usar accesos de siete o treinta días y fechas personalizadas, con máximo noventa días. Las opciones disponibles respetan sus módulos y sucursales. Un cero puede indicar ausencia de operaciones para ese filtro; no modifique saldos desde el Dashboard.')),
    h('11. Reportes', imagen('reportes', 'Selección de reporte, filtros y acciones de impresión/exportación.') + p('Entre en Reportes, elija el tipo, ajuste los filtros disponibles y consulte. Para reportes con fechas, el período máximo es de 366 días. El empleado consulta su sucursal; el administrador puede seleccionar una o todas.')
    + p('Exportar CSV descarga el resultado completo de los filtros. Imprimir prepara la vista consultada con su contexto; use Guardar como PDF del navegador si necesita un archivo. Verifique qué página está mostrando antes de imprimir una consulta paginada.')
    + tabla(['Grupo', 'Reportes disponibles'], [('Inventario', 'Existencias; valoración por categoría; kardex.'), ('Ventas', 'Ventas por día; productos más vendidos; ventas por cliente.'), ('Operaciones y control', 'Ingresos por proveedor; flujo de traslados; auditoría de anulaciones.')])),
    h('12. Recuperación y mensajes frecuentes', tabla(['Situación', 'Qué hacer'], [('Respuesta de venta incierta', 'No cree otra venta ni cambie el detalle. Use Verificar venta registrada o Reintentar facturación de la misma operación.'), ('Sucursal cambiada', 'Revise el nombre activo y recargue la consulta. La operación anterior no se envía automáticamente a otra ubicación.'), ('Stock insuficiente', 'Compruebe sucursal y cantidades. Si la mercadería está en otra ubicación, tramite un traslado.'), ('Sin permiso', 'Solicite al administrador revisar módulos y sucursales asignadas.'), ('Código incorrecto', 'Use su código de facturación. No pruebe códigos de otras cuentas.'), ('Servicio externo no disponible', 'Conserve el documento. Reintente la integración de prueba cuando vuelva a estar disponible.'), ('Anulación rechazada', 'Revise estado, sucursal y stock necesario para la reversión.')]))
    + h('Buenas prácticas de operación', lista(['Revise la sucursal al comenzar cada turno y antes de recibir traslados.', 'No comparta usuario, contraseña o código de facturación.', 'Use el historial para confirmar un resultado dudoso antes de repetirlo.', 'Cierre sesión al terminar. Contacte al administrador si detecta saldos o documentos inesperados.'])),
])

guardar('05-Evidencias', 'Evidencias de pruebas', [
    portada('Evidencias de pruebas', 'Casos, resultados locales y trazabilidad de la verificación'),
    h('1. Alcance y procedencia', p('Estas evidencias corresponden a la versión local documentada el 24/10/2026. Los conteos se extrajeron de los resultados Newman existentes y del registro de verificación en MEMORY.md. Las capturas nuevas se obtuvieron de libreria_demo, sin registrar ventas ni modificar catálogos. No son evidencias de publicación en Internet.'))
    + tabla(['Comprobación', 'Resultado registrado'], [('Backend', '193 pruebas y 1,538 aserciones correctas en la última suite completa registrada.'), ('Rutas', str(len(rutas_publicas)) + ' rutas API inventariadas desde route:list --json.'), ('Navegador', 'Ocho suites funcionales verificadas: auth, catálogos, inventario, dashboard, reportes, integraciones, sucursales y caja.'), ('Adaptación horizontal', '11 módulos comprobados a 1920, 1365 y 390 px sin desbordamiento.'), ('Herramientas', 'Pint, compilación Vite, pruebas Node del lector/dinero y contratos de APIs correctos según MEMORY.md.')])
    + aviso('No se publican los JSON crudos de Newman: pueden contener JWT, cookies o credenciales temporales. docs/evidencias/resultados-verificados.json conserva solo conteos y alcance; rutas-api.json contiene metadatos públicos de rutas.')
    + p('Empaquetado adicional verificado: imagen PHP/Apache con Vue compilado, 34 comprobaciones locales y Postman con 29 solicitudes/48 aserciones sin fallos. El navegador renovó la sesión al cargar nuevamente la aplicación. Sesión y límite de intentos se conservaron tras reiniciar el contenedor. Alcance: HTTP de loopback y MongoDB local; no HTTPS público. Registro sanitizado: docs/evidencias/publicacion-local.json.'),
    h('2. Resultados de Postman / Newman', tabla(['Suite', 'Solicitudes', 'Aserciones', 'Fallos'], [(r['suite'], r['solicitudes'], r['aserciones'], r['fallos']) for r in resultados]) + p('Cada colección se conserva en docs/postman. La demostración se verifica por separado de las ocho suites temporales. Se probaron respuestas válidas e inválidas, permisos y documentos inexistentes; una respuesta de error esperada es una prueba correcta cuando satisface la aserción.'))
    + h('Reproducir', codigo('python docker/demostracion.py --verificar\npython frontend/tests/run-browser.py --help\n# Seguir .ia/TESTING.md para las suites temporales de navegador/Postman.')),
    h('3. Casos que cubren las reglas', tabla(['Caso', 'Resultado esperado comprobado'], [('PIN, sesión y módulo', 'Rechaza PIN inválido, sesión revocada y usuario sin permiso.'), ('Venta sin stock / simultánea', 'No deja saldos negativos ni documento parcial.'), ('UUID repetido', 'Mismo contenido recupera documento; contenido distinto devuelve conflicto.'), ('Precio cambiado', 'Rechaza total esperado obsoleto antes de escribir inventario.'), ('Código exacto', 'Conserva ceros; busca incluso fuera de los primeros 500 productos.'), ('Scanner 5*código + Enter', 'Agrega cantidad solicitada y acumula lecturas rápidas.'), ('Sucursales múltiples', 'Cambia contexto por sesión; bloquea contexto de petición anterior.'), ('Traslado', 'Solo origen anula, solo destino confirma; estados terminales no se duplican.'), ('Reportes', 'Respeta filtros y sucursal; CSV completo; importes y exportación seguros.'), ('APIs de prueba', 'Éxito, rechazo, timeout, respuesta incoherente e idempotencia persistida local.'), ('Banguat', 'Consulta real HTTPS y pruebas controladas de XML, fechas, tasas y fallos.')]))
    + p('Las pruebas transaccionales y de permisos se ejecutan en bases temporales con guardas de nombre. Las cuentas y datos de pruebas son ficticios. La ejecución no sustituye una prueba con escáner físico, una revisión de seguridad externa o la validación pendiente en hosting.'),
    h('4. Evidencia visual · caja', imagen('venta-detalle', 'Caja abierta con cantidad*código y detalle ficticio.') + imagen('venta-cobro', 'Cobro con total, efectivo recibido y cambio, antes de autorizar.', 'dialogo') + p('Las imágenes del manual completan acceso, inicio, usuarios, catálogo, ingresos, traslados, dashboard y reportes. Se conservaron sin contraseñas, códigos personales ni tokens visibles.')),
    h('5. Evidencia visual · consultas', imagen('dashboard', 'Dashboard de la base de demostración.') + imagen('reportes', 'Reportes y filtros con el mismo conjunto ficticio.')),
    h('6. Verificación pendiente de publicación', lista(['Registrar URL pública de la interfaz y URL base de API realmente desplegadas.', 'Demostrar conexión del backend con MongoDB en la nube sin mostrar su URI privada.', 'Ingresar y renovar sesión en HTTPS con roles diferentes.', 'Completar un ingreso, una venta y un traslado con aislamiento por sucursal.', 'Generar y recuperar comprobantes de las dos APIs independientes tras reiniciar sus servicios.', 'Consultar Banguat desde el hosting y probar el tratamiento de un fallo.', 'Exportar los nueve reportes y revisar la presentación desde el entorno publicado.'], True)
    + p('No se declara concluida la fase 16 hasta adjuntar esos resultados. Las cuentas de prueba y credenciales de administración se entregan por un canal privado indicado por el docente. No se incluyen en el repositorio público.')
    + h('Limitación académica explícita', p('El enunciado solicita participación e historial de tres estudiantes. Esta entrega documenta a un único autor por la situación informada. La consideración de esta modalidad corresponde al docente.'))),
])

guardar('06-Presentacion', 'Presentación final', [
    portada('Librería San Francisco', 'Presentación y guía de defensa · Proyecto individual'),
    h('Problema y solución', p('Controlar qué se compra, qué se vende y dónde está cada unidad.') + lista(['Catálogo común e inventario separado por sucursal.', 'Tres procesos integrados: abastecimiento, venta y traslado.', 'Seis módulos principales de negocio, más sucursales y nueve reportes.', 'Usuarios con permisos y múltiples sucursales asignadas.']))
    + p('Defensa: explicar por qué no basta guardar una cantidad global en el producto.'),
    h('Diseño y consistencia', lista(['Vue 3 / Axios → Laravel → Repository → MongoDB.', 'Reglas en Services; controladores delgados; FormRequest valida.', 'Transacciones: documento + existencia + kardex.', 'UUID e índices evitan duplicados; importes en centavos y Decimal128.']))
    + p('Defensa: describir qué ocurre si dos cajeros intentan vender la última unidad al mismo tiempo.'),
    h('Demostración 1 · ingreso y venta', lista(['Seleccionar sucursal y registrar un ingreso de proveedor.', 'Abrir Ventas y leer 2*000-DEMO-001 + Enter.', 'Seleccionar cliente, revisar total y efectivo.', 'Confirmar cobro, autorizar con código propio y consultar comprobante.', 'Mostrar el saldo y los movimientos correspondientes.'], True)
    + p('Preparación: usar datos ficticios, accesos privados y disponibilidad de los servicios. No mostrar archivos de credenciales al proyectar.')),
    h('Demostración 2 · traslado y permisos', lista(['Enviar mercadería desde origen hacia destino.', 'Mostrar que origen puede anular pero no confirmar.', 'Cambiar a destino y confirmar la recepción.', 'Consultar kardex en ambas sucursales y el reporte de traslados.', 'Entrar como empleado y comprobar módulos y sucursal autorizados.'], True)
    + p('Defensa: explicar por qué enviado todavía no suma stock en destino y cómo se evita confirmar dos veces.')),
    h('Tres integraciones externas', tabla(['API', 'Qué se demuestra'], [('Banguat SOAP', 'Consulta HTTPS real, fecha/referencia y error controlado.'), ('Facturación de prueba', 'Petición HTTP a otro proceso; comprobante simulado e idempotencia.'), ('Pagos de prueba', 'Otro servicio y contrato; respuesta simulada sin movimiento bancario.')]))
    + p('Responsable: ' + AUTOR + '. Explicar el dato enviado, la autenticación, la validación de respuesta y el tratamiento de timeout. No confundir un comprobante académico con una factura fiscal.'),
    h('Reportes, pruebas y seguridad', lista(['Nueve reportes distintos, con filtros, CSV e impresión.', 'JWT corto y renovación HttpOnly; permisos en backend.', '193 pruebas / 1,538 aserciones de backend en la verificación registrada.', 'Postman, navegador, lector simulado y contratos HTTP.', 'Manual ilustrado, diseño y fuentes editables en docs.']))
    + p('Defensa: mostrar un caso inválido y otro sin permiso, y explicar por qué ocultar botones no reemplaza la autorización.'),
    h('Estado de entrega', tabla(['Listo localmente', 'Pendiente antes de la entrega pública'], [('Procesos, roles, sucursales, caja, reportes y APIs locales.', 'Crear cuentas gratuitas y configurar almacenamiento persistente.'), ('Datos ficticios, pruebas y documentación.', 'Desplegar y verificar frontend, API, MongoDB y proveedores externos.'), ('Autor y responsabilidades documentados.', 'Registrar URLs reales y entregar accesos privados al docente.')]))
    + p('Trabajo individual: ' + AUTOR + ' · ' + CARNE + '. El enunciado prevé equipos de tres; la modalidad individual se registra para consideración del docente.')
    + p('Cerrar la demostración con una operación completa en línea cuando la fase de publicación esté verificada.'),
], True)

(DOCS / 'documentos.json').write_text(json.dumps({'autor': AUTOR, 'carne': CARNE, 'fecha': '2026-10-24', 'documentos': documentos}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Fuentes editables generadas:', len(documentos))
print('Rutas API:', len(rutas_publicas), '· reglas de negocio:', len(REGLAS))
print('Evidencias Postman sanitizadas:', len(resultados), 'suites, todas sin fallos.')
