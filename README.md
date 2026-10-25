# Librería San Francisco — ERP web (Stack 3)

Inventario, ventas y traslados entre sucursales.
MongoDB (Docker) · Laravel + mongodb/laravel-mongodb · Vue 3 + Vite + Axios.

## Estructura
backend/ (API) · frontend/ (interfaz) · database/ (scripts y diccionario de datos) · docker/ (configuración de contenedores) · docs/ (diagramas, manuales, evidencias)

## Ejecución local
1. `docker compose up -d --wait` (MongoDB local como replica set de un nodo rs0).
2. Desde backend/, preparar los índices: `php artisan migrate --database=mongodb --path=database/migrations/2026_10_23_232450_crear_indices_inventario.php --path=database/migrations/2026_10_24_054617_agregar_indices_dashboard.php`.
3. Terminal 1: cd backend, luego php artisan serve.
4. Terminal 2: cd frontend, luego npm run dev (npm.cmd en PowerShell si npm.ps1 está bloqueado).

## Configuración

Para una instalación nueva, copie backend/.env.example a backend/.env y frontend/.env.example a frontend/.env. Complete DB_URI con las credenciales del contenedor local y ejecute `php artisan key:generate` y `php artisan libreria:jwt-clave` desde backend/. No sobrescriba archivos .env de una instalación existente. Después de cambiar variables, ejecute `php artisan config:clear` y reinicie Vite si cambió frontend/.env o vite.config.js.

El frontend usa VITE_API_URL=/api y el proxy de Vite hacia VITE_API_PROXY_TARGET. Así la cookie de renovación pertenece al mismo origen que la interfaz. FRONTEND_ORIGINS enumera los orígenes permitidos para la sesión; por defecto, localhost:5173 y 127.0.0.1:5173 con esquema http. La publicación deberá conservar el acceso a /api mediante un proxy del mismo origen o una configuración equivalente del mismo sitio.

## Primer acceso y Usuarios

Desde backend/, ejecutar `php artisan libreria:crear-admin --generar-clave`. Usa una sucursal activa existente; --sucursal permite escoger su ID. Solo para una instalación vacía, agregar --crear-sucursal permite crear Sucursal Central. El comando no reemplaza cuentas existentes y no habilita registro público.

El usuario inicial es admin y su contraseña aleatoria se guarda en backend/storage/app/private/acceso-inicial.txt, excluido de Git. En producción no se genera ese archivo: se solicita la contraseña de forma oculta o se toma LIBRERIA_ADMIN_PASSWORD del entorno. El superusuario administra cuentas desde Usuarios; contraseñas y códigos de facturación se almacenan con hash y nunca aparecen en los listados. Cambiar la contraseña, rol, sucursal, estado o módulos invalida las sesiones anteriores. Una sucursal con usuarios activos no puede desactivarse antes de reasignarlos o desactivarlos.

## Avance funcional

Ventas abre directamente una caja con buscador, detalle y resumen. Un lector que envía código + Enter agrega una unidad; `5*001234` + Enter agrega cinco. Los códigos conservan ceros iniciales y se buscan de forma exacta; las lecturas rápidas se procesan en orden. La búsqueda por nombre permite seleccionar el producto con el ratón. Las cantidades y el stock corresponden a la sucursal activa.

**Agregar cliente (NIT)** permite asignar un cliente existente por NIT o buscarlo por nombre. Quien tenga permiso Clientes también puede registrar uno nuevo desde la caja. Sin cliente asignado se utiliza Consumidor Final. **Guardar (facturar)** revisa precios/stock, abre total/efectivo/cambio y, al confirmar el cobro, solicita el código de facturación del usuario. Solo entonces registra la venta y muestra su comprobante imprimible. Si el total cambia antes de guardar, el servidor rechaza la operación para que se revise el cobro.

**Historial y kardex** conserva la consulta de documentos, impresión, anulación autorizada e integración de factura de prueba. El borrador de caja se conserva únicamente en memoria, separado por usuario y sucursal, al alternar vistas; se limpia al salir de la cuenta. No persiste tras recargar o cerrar el navegador. Las lecturas no mueven stock: el descuento se realiza al registrar la venta. Ante una respuesta incierta, detalle, efectivo y UUID quedan bloqueados para reintentar la misma operación o verificar si ya existe, evitando duplicados. El código de facturación no se guarda en Web Storage.

Pruebas de caja: `node --test tests/caja.test.mjs` desde frontend/ verifica interpretación de códigos y centavos. El ejecutor de navegador incluye caja.browser.mjs; caja.postman_collection.json comprueba búsquedas exactas, importe esperado, cambio, idempotencia y permisos. Las colecciones siguen usando exclusivamente bases temporales.

Usuarios permite asignar varias sucursales y elegir una principal al iniciar sesión. Las cuentas antiguas con una sola sucursal continúan funcionando. Un empleado puede alternar entre sus sucursales activas asignadas desde el selector situado debajo de su nombre en la cabecera; el superusuario puede alternar entre todas las sucursales activas. Cambiar la asignación desde Usuarios invalida las sesiones existentes.

La selección se guarda por sesión, conserva la principal del usuario y se mantiene al recargar o renovar el acceso. Al alternar se cierran las vistas/formularios anteriores y se consultan los datos de la sucursal nueva. El selector se bloquea mientras hay una operación de escritura en curso. Las peticiones de una pestaña con sucursal anterior reciben un conflicto de contexto, actualizan la sesión y no se repiten automáticamente.

El Catálogo abre las existencias de la sucursal activa; el administrador conserva sus filtros de consulta global. Cada ingreso, venta o traslado nuevo pertenece a la sucursal activa, también para el superusuario. El inventario está separado por producto y sucursal: una venta no toma unidades de otras tiendas y cambiar de sucursal no transfiere existencias.

Los traslados pendientes solo se anulan desde su origen y solo se confirman desde su destino. Esta regla también rige para el superusuario. Un usuario asignado a ambas sucursales puede enviar desde una y, después de alternar a la otra, recibir; no puede anular estando en el destino. Los permisos se validan en el backend y la interfaz muestra solo la acción correspondiente.

API: Usuario admite sucursal_ids (lista de IDs, al menos uno) y sucursal_id (principal, incluida en la lista). Omitir la lista conserva compatibilidad con clientes anteriores. POST /api/auth/sucursal recibe sucursal_id, valida acceso y devuelve un access token/perfil actualizados sin cambiar la cookie de renovación. En /auth/me, sucursal_id corresponde a la activa y sucursal_principal_id a la principal. La interfaz envía X-Sucursal-Id para detectar vistas obsoletas. La colección sucursales.postman_collection.json y el script sucursales.browser.mjs verifican asignación, alternancia, inventarios y recepción.

Sucursales, Proveedores, Clientes, Catálogo y Usuarios cuentan con formularios, búsqueda, edición y eliminación. Login JWT y permisos protegen la API y la navegación. El superusuario dispone de todos los módulos; un empleado opera en los módulos asignados y su sucursal. Usuarios es exclusivo del superusuario. El empleado consulta existencias de su sucursal; el superusuario puede consultar totales o elegir una sucursal.

La fase 10 incorpora Ingresos por proveedor, Ventas con efectivo/cambio y Traslados con envío/recepción, comprobantes imprimibles y kardex. Documento, correlativo, existencias y movimientos se confirman en una transacción MongoDB. El código de facturación se valida al guardar cada venta; configúrelo en Usuarios antes de vender. Solo el superusuario anula ingresos y ventas; los traslados pendientes se anulan en origen y se confirman en destino. Consulte [Inventario](docs/INVENTARIO.md) para contratos, permisos y reintentos.

Inicio muestra la bienvenida, nombre y sucursal del usuario y siempre se abre después de un login correcto. Inicio ocupa el primer lugar de la barra lateral y Ventas aparece inmediatamente debajo; el botón de casa de la cabecera vuelve a Inicio. Al recuperar una sesión válida se conserva la sección abierta.

La fase 11 se encuentra en Dashboard, separado de Inicio: existencias/alertas, ventas e ingresos por período, gráfica diaria consultable, documentos recientes y traslados pendientes. Filtra sucursal y hasta 90 días en America/Guatemala. Cada usuario ve únicamente los indicadores de sus módulos; un empleado queda limitado a su sucursal. Dashboard se habilita automáticamente con Catálogo, Ventas, Ingresos o Traslados. Consulte [Dashboard](docs/DASHBOARD.md).

La fase 12 incorpora nueve reportes distintos con filtros, totales completos, paginación, impresión por página y CSV completo. Exige el permiso Reportes y restringe empleados a su sucursal; la valoración mantiene precisión decimal y el CSV neutraliza fórmulas en textos. Consulte [Reportes](docs/REPORTES.md).

El access token dura 15 minutos y permanece en memoria. El refresh token rota en una cookie HttpOnly, SameSite=Lax (Secure en producción), con vencimiento absoluto de 7 días. La interfaz recupera la sesión al recargar, comparte una renovación entre solicitudes simultáneas y revoca la sesión al salir. Los POST de sesión requieren X-Requested-With: XMLHttpRequest y un origen permitido cuando el cliente envía Origin.

## Pruebas

Las integraciones de la fase 13 se ejecutan como se indica abajo. El ejecutor de pruebas inicia servicios independientes en 8111/8112 y un servidor SOAP controlado en 8113; este último solo existe en NODE_ENV=test. Las pruebas no dependen de Internet ni usan las credenciales de las APIs locales del usuario.

- Backend: desde backend/, ejecutar `php artisan test --compact`. Los catálogos requieren MongoDB local disponible y usan bases aleatorias aisladas; no modifican la base del proyecto. No se ejecutan migraciones relacionales.
- Frontend: desde frontend/, ejecutar `npm run build`.
- Postman: importar las cinco colecciones de docs/postman/ (auth, catalogos, inventario, dashboard y reportes). Configurar base_url, usuario y password de un superusuario de pruebas; auth e inventario requieren password_empleado, e inventario también su codigo de facturación. Dashboard y reportes usan fixtures protegidos descritos en sus documentos. Cubren casos válidos, inválidos, sin permiso, sin sesión e IDs inexistentes. Usar una base aislada: las pruebas de seguridad revocan sesiones y el inventario conserva sus documentos y movimientos como historial.
- Navegador: los scripts de frontend/tests/ utilizan CDP de Edge/Chrome sin dependencias adicionales del frontend. Iniciar un perfil exclusivo con --headless=new, --remote-debugging-port=9222 y --user-data-dir apuntando a frontend/test-results/edge-profile. Desde frontend/, ejecutar `python tests/run-browser.py`: prepara BD/caché temporales, inicia backend en 8002 y frontend en 5175, prueba autenticación/Usuarios, catálogos, inventario, dashboard y reportes, detiene sus servidores y elimina únicamente su BD de pruebas. La caché de intentos temporal se limpia entre suites independientes mediante un guard de BD/ruta; los límites de la aplicación se conservan. MongoDB debe estar disponible. Capturas, PDF, CSV y logs quedan en frontend/test-results/ (ignorado).
- Newman: el mismo ejecutor acepta `python tests/run-browser.py auth.postman_collection.json catalogos.postman_collection.json`. Requiere Newman en frontend/node_modules/.cache/newman; puede prepararse desde la raíz con `npm exec --cache ./frontend/node_modules/.cache/newman --yes --package=newman -- newman --version`. Esta herramienta de pruebas no modifica las dependencias del frontend.

Además: `node --test docker/apis/simulador.test.mjs` desde la raíz verifica contratos HTTP, autenticación, importes, solicitudes simultáneas y persistencia tras reiniciar ambos servicios. La sexta colección, `integraciones.postman_collection.json`, se ejecuta con `python tests/run-browser.py integraciones.postman_collection.json` desde frontend/. La ejecución de navegador predeterminada incluye también integraciones.

## Fase 13 — tres servicios externos

- Facturación de prueba: proceso Node independiente en 127.0.0.1:8101, POST /v1/facturas. Desde el comprobante de una venta vigente, usar **Generar factura de prueba**. El resultado FAC-DEMO se guarda en la venta y se puede imprimir. Es una simulación académica SIN VALOR FISCAL; no emite FEL ni contacta a SAT.
- Pagos de prueba: otro proceso Node, credencial e historial propios en 127.0.0.1:8102, POST /v1/comprobantes-pago. Desde un ingreso vigente, usar **Generar pago de prueba**. Guarda PAGO-DEMO en el ingreso, SIN MOVIMIENTO BANCARIO. No liquida obligaciones ni altera cuentas, efectivo o inventario.
- Banco de Guatemala: consulta HTTPS SOAP TipoCambioDia al [servicio oficial](https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx?op=TipoCambioDia). Abrir **Consultar tipo de cambio USD / GTQ** en un formulario de venta o ingreso. El importe opcional se convierte con aritmética decimal y se muestra junto con la fecha de referencia. El documento continúa en GTQ. Solo se envía una consulta pública sin datos de clientes, productos ni usuarios. Caché de 30 minutos por día de Guatemala; no se inventa una tasa si falla el servicio.

Preparación local, sin dependencias adicionales:

1. Desde backend/, ejecutar `php artisan libreria:apis-demo --no-interaction`. Conserva las claves existentes y genera las que falten en backend/storage/app/private/apis-demo/config.json, ignorado por Git. Nunca se muestran claves en la interfaz. El comando se limita a local/testing.
2. Desde la raíz, ejecutar `node docker/apis/iniciar.mjs` en una terminal adicional. Inicia dos procesos HTTP distintos, enlazados únicamente a 127.0.0.1. Cada /salud informa su tipo y entorno simulado. Cerrar la terminal con Ctrl+C detiene sus procesos. No iniciar dos copias.
3. Reiniciar los servicios con el mismo historial conserva la idempotencia. No eliminar config.json, facturacion.json ni pagos.json para resolver fallos: hacerlo pierde las credenciales o el historial remoto. Los archivos son privados y quedan fuera de Git.

Laravel lee las claves privadas solo en local/testing. FACTURACION_API_URL/TOKEN y PAGOS_API_URL/TOKEN permiten configuración explícita; en producción es obligatoria y exige publicar/proteger por separado los servicios de demostración. No usar estos simuladores como infraestructura fiscal o bancaria. La publicación se resuelve en la fase 16.

Los tres endpoints del ERP requieren JWT: POST /api/ventas/{id}/factura-prueba exige Ventas; POST /api/ingresos/{id}/pago-prueba exige Ingresos; GET /api/integraciones/tipo-cambio admite Catálogo, Ventas o Ingresos. Comparten límite de 30 solicitudes por usuario/IP/minuto. Empleados solo pueden generar pruebas de documentos de su sucursal. Documento inexistente/ajeno: 404; sin permiso: 403; anulado/solicitud en curso: 409; entrada/rechazo externo: 422; respuesta externa incoherente: 502; desconexión o servicio sin configurar: 503.

Las APIs reciben una copia del encabezado/detalle original en centavos, una credencial de servicio y una clave de idempotencia estable por documento. Nunca reciben JWT de usuario, contraseña ni código de facturación. Validan que las líneas y el total coincidan y rechazan reutilizar la misma clave con otro contenido. El backend evita llamadas simultáneas mediante una reserva de 30 segundos, oculta del JSON. Un timeout permite repetir la misma solicitud y recuperar el mismo comprobante, incluso después de reiniciar el proveedor. Solo se confirma una respuesta simulada que corresponde al documento/importe esperado. No hay reintento automático de los POST. Anular durante una llamada conserva la respuesta de prueba como historial del documento anulado; no crea movimientos adicionales.

### HTTPS de Banguat en Windows

La validación TLS permanece activa. Si PHP informa una cadena de certificados no confiable aunque Windows acepte el sitio, se puede usar un archivo PEM de autoridades del sistema mediante BANGUAT_CA_BUNDLE. En desarrollo local también se detecta backend/storage/app/private/apis-demo/ca-windows.pem. Desde PowerShell, en la raíz:

```powershell
New-Item -ItemType Directory -Force backend/storage/app/private/apis-demo | Out-Null
$certificadosBanguat = Get-ChildItem Cert:\CurrentUser\Root,Cert:\LocalMachine\Root | Sort-Object Thumbprint -Unique
$pemBanguat = ($certificadosBanguat | ForEach-Object { '-----BEGIN CERTIFICATE-----' + [Environment]::NewLine + [Convert]::ToBase64String($_.RawData,[Base64FormattingOptions]::InsertLineBreaks) + [Environment]::NewLine + '-----END CERTIFICATE-----' }) -join [Environment]::NewLine
[IO.File]::WriteAllText((Join-Path (Get-Location) 'backend/storage/app/private/apis-demo/ca-windows.pem'),$pemBanguat,[Text.Encoding]::ASCII)
```

El PEM contiene certificados públicos de las autoridades que ya confía Windows, sin claves privadas. Se conserva localmente y debe renovarse si cambia el almacén de confianza. Después de cambiar configuración, ejecutar `php artisan config:clear` desde backend/. En otros servidores configurar su almacén de CA o un PEM apropiado para ese entorno; no desactivar la verificación. Referencia: [certificados de CA en cURL](https://curl.se/docs/sslcerts.html).

## Fase 14 — demostración persistente y separada

Desde la raíz, ejecutar `python docker/demostracion.py`. Prepara la base **libreria_demo** y abre sus servicios en http://127.0.0.1:5176 (interfaz) y 127.0.0.1:8003 (API). MongoDB y las dependencias de backend/frontend deben estar disponibles. Si un puerto está ocupado, el ejecutor se detiene sin reemplazar procesos. Ctrl+C cierra únicamente los dos servidores que inició; la base y sus accesos se conservan.

La interfaz identifica **Modo demostración · Datos ficticios**. Esta instancia usa su propia clave JWT, cookie libreria_demo_refresh y caché. No cambia los .env ni los servicios habituales. La cookie habitual libreria_refresh se conserva aunque ambas interfaces se abran en 127.0.0.1 con distintos puertos.

La preparación también puede ejecutarse sola desde backend/: `php artisan libreria:demo --no-interaction`. Rechaza producción, la base actualmente configurada, nombres ajenos a libreria_demo y bases ocupadas sin su marca de preparación. El sufijo libreria_demo_test_<16 caracteres hexadecimales> se reserva para pruebas automatizadas. No hay opción de vaciado ni reinicio destructivo.

Contenido inicial:

| Catálogo / proceso | Registros |
| --- | ---: |
| Sucursales | 5 |
| Proveedores | 5 |
| Clientes | 5 |
| Productos | 10 |
| Usuarios | 5 |
| Ingresos | 6 (uno anulado) |
| Ventas | 6 (una anulada) |
| Traslados | 3 (confirmado, pendiente y anulado) |
| Movimientos de kardex | 29 |

Hay 123 unidades disponibles y dos en tránsito. Los nueve reportes tienen resultados: existencias, valoración, kardex, ventas diarias, productos vendidos, ventas por cliente, ingresos por proveedor, traslados y anulaciones. Los importes y el inventario se obtienen de los servicios normales: no se escriben saldos ni movimientos a mano. Los datos se generan con la fecha de preparación; para demostraciones posteriores elegir ese período en los reportes.

Las credenciales aleatorias se guardan exclusivamente en **backend/storage/app/private/demo/libreria_demo/accesos.json**, excluido de Git. También contiene los códigos de facturación y la clave JWT de la demo. No se imprimen contraseñas en la terminal ni se añade información privada al frontend.

| Usuario | Acceso | Sucursal |
| --- | --- | --- |
| admin.demo | Superusuario, todos los módulos | DEMO - Central |
| caja.demo | Ventas y Clientes | DEMO - Central |
| bodega.demo | Catálogo, Ingresos y Traslados | DEMO - Central |
| reportes.demo | Solo Reportes | DEMO - Central |
| destino.demo | Traslados | DEMO - Norte |

Volver a ejecutar el comando conserva las cuentas, contraseñas, IDs, operaciones y cambios manuales. Una carga interrumpida puede reanudarse: utiliza identificadores estables de operación y los procesos existentes para evitar duplicar inventario. Una reserva de diez minutos excluye preparaciones simultáneas. Después de un cierre abrupto, esperar su vencimiento y repetir el comando. Conservar accesos.json: si se pierde o cambia su contenido, la huella de preparación impide sobrescribir las cuentas de la base existente. Cambiar una contraseña desde Usuarios no modifica automáticamente ese archivo privado.

Verificación desde la raíz: `python docker/demostracion.py --verificar`. Requiere el perfil exclusivo Edge/CDP y Newman indicados en Pruebas. Ejecuta la colección demostracion.postman_collection.json y el script de navegador contra la demo, comprueba los conteos iniciales, reportes, permisos y móvil; después cierra sus servidores y conserva la base. Esta comprobación está pensada para el conjunto inicial: si se agregan operaciones manuales, los conteos esperados dejan de coincidir. No reinicia ni borra datos para hacer pasar las pruebas. Capturas y reporte de Newman quedan en frontend/test-results/ ignorado.

Las APIs simuladas de la fase 13 pueden iniciarse aparte con `node docker/apis/iniciar.mjs`; la preparación de datos no llama a APIs externas ni genera pagos o facturas de prueba automáticamente.

## Fase 15 — documentación y manuales

La entrega está indexada en [docs/ENTREGA.md](docs/ENTREGA.md): seis PDF con documento del proyecto, diseño, manual técnico, manual de usuario ilustrado, evidencias y presentación. Incluye fuentes editables HTML/CSS/SVG y herramientas reproducibles de captura e impresión. Autor: José Alfredo Davila Abrego, carné 7490236074; se registra el desarrollo individual informado, sin inventar otros integrantes.

## Fase 16 — preparación de publicación gratuita

[docs/PUBLICACION.md](docs/PUBLICACION.md) describe Render Free y MongoDB Atlas Free. El Dockerfile de docker/publicacion sirve Vue compilado y Laravel bajo el mismo origen; render.yaml prepara la aplicación y docker/publicacion/render-apis.yaml los dos proveedores independientes. La publicación real sigue pendiente de crear cuentas, desplegar y verificar las URLs.

Los proveedores conservan el modo local por archivos. Para publicación usan el driver oficial MongoDB 7.7, autorizado por el usuario, con API_MONGO_URI y API_MONGO_DATABASE; requieren Node 24 y `npm ci` en docker/apis. API_HOST=0.0.0.0 y PORT permiten el enlace del hosting. En producción pública se rechaza iniciar sin historial MongoDB persistente. Laravel permite CACHE_STORE=mongodb sin cambiar su predeterminado local, usando el adaptador que ya incluye mongodb/laravel-mongodb.

`python docker/publicacion/preparar_atlas.py --preparar` genera secretos en el directorio privado de publicación, ignorado por Git. No muestra las claves ni cambia los .env existentes. --cargar solo admite Atlas y carga la demostración en libreria_demo con almacenamiento privado separado; --exportar prepara variables privadas para los paneles. No hay carga pública ni creación automática de recursos de pago.

Pruebas adicionales: `node --test docker/apis/simulador.test.mjs docker/apis/simulador-mongo.test.mjs` y `php artisan test --compact tests/Feature/CachePublicacionTest.php` desde backend para esta última. [database/RESPALDO.md](database/RESPALDO.md) explica una recuperación en otra base sin borrar datos existentes. MEMORY.md distingue los resultados comprobados de los pendientes.
