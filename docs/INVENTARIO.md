# Ingresos, ventas, traslados y kardex

## Preparación local

`docker compose up -d --wait` conserva el volumen mongodb_libreria_data y arranca MongoDB en el puerto 27018, como replica set rs0. El keyfile se genera dentro del volumen de configuración; no se guarda en el repositorio. El nodo anuncia localhost:27018, válido para el backend ejecutado en Windows y para el propio contenedor. Esta configuración de un nodo sirve para el desarrollo local; la publicación se configura en su fase correspondiente.

Desde backend/, ejecutar:

```powershell
php artisan migrate --database=mongodb --path=database/migrations/2026_10_23_232450_crear_indices_inventario.php
php artisan route:list --path=api
```

No ejecutar las migraciones relacionales predeterminadas para preparar inventario. Las escrituras se rechazan con 503 si falta la preparación del esquema. InventarioService rechaza movimientos fuera de una transacción. La conversión local se hizo conservando el volumen y un respaldo privado previo; nunca requiere eliminar volúmenes.

Referencias: [transacciones en Laravel MongoDB](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/interact-data/transactions/) y [conversión de standalone a replica set](https://www.mongodb.com/docs/v8.0/tutorial/convert-standalone-to-replica-set/).

## Operación y permisos

| Proceso | Efecto | Anulación |
| --- | --- | --- |
| Ingreso | Suma en sucursal, exige proveedor activo y productos de ese proveedor | Superusuario; resta en la sucursal original y exige stock suficiente |
| Venta | Resta en sucursal, valida PIN propio, efectivo y precios del servidor | Superusuario; devuelve las unidades originales |
| Traslado enviado | Resta en origen, deja unidades en tránsito | Solo sucursal activa de origen; devuelve las unidades si aún está enviado |
| Traslado confirmado | Suma en destino una vez | No admite anulación ni segunda recepción |

Todo usuario registra documentos en su sucursal activa. Puede alternar desde la cabecera entre las sucursales autorizadas. El empleado puede leer traslados donde su sucursal activa sea origen o destino; solo el destino confirma y solo el origen anula. Esta regla también se aplica al superusuario: debe alternar a la sucursal correspondiente. Cada endpoint exige su módulo, aunque la interfaz o el cliente HTTP soliciten otro. Los selectores de productos/clientes/destinos tienen permisos independientes de los CRUD: vender no requiere permiso para administrar el catálogo o los clientes. El selector de ventas no entrega costos si no se dispone de ingresos.

Los importes documentales se calculan con centavos enteros, hasta Q 99,999,999.99. El ingreso utiliza el costo vigente del catálogo y la venta el precio de venta vigente. Cada detalle admite hasta 100 productos distintos, con cantidades enteras entre 1 y 100000. No se aceptan precios en las líneas ni productos repetidos. Se conservan códigos de barras como texto.

## API

Para cada prefijo `/api/ingresos`, `/api/ventas` y `/api/traslados`:

- GET raíz: hasta 200 documentos recientes, con filtros sucursal_id y estado.
- GET /{id}: documento y detalle; una referencia de otra sucursal se oculta con 404.
- POST raíz: registra documento y kardex; responde 201.
- POST /{id}/anular: requiere motivo de 3 a 300 caracteres; responde 200 o 409 si el estado ya no permite anular.

POST /api/traslados/{id}/confirmar requiere motivo como observación de recepción. GET /api/inventario/movimientos filtra por sucursal_id/producto_id y devuelve hasta 200 movimientos recientes. Cada movimiento incluye cantidad con signo, antes, despues, documento/numero, motivo y responsable.

Todos los POST de registro requieren operacion_id (UUID), items (producto_id y cantidad) y sucursal_id opcional, que por defecto es la activa en la sesión y debe coincidir con ella. Ingresos añade proveedor_id y motivo; traslados añade destino_id y motivo; ventas añade codigo (string de cuatro dígitos), efectivo con hasta dos decimales y cliente_id opcional (sin cliente, Consumidor Final/CF).

POST /api/auth/sucursal recibe sucursal_id y cambia únicamente la sucursal activa de esa sesión. Devuelve un access token y perfil actualizados; no modifica la principal del usuario ni rota el refresh. Los usuarios antiguos con una única sucursal mantienen su acceso. La interfaz envía X-Sucursal-Id: una pestaña con contexto anterior recibe 409/SUCURSAL_CAMBIADA y actualiza su sesión sin repetir automáticamente la operación.

El inventario sigue separado por producto_id + sucursal_id, con índice único. Vender en una sucursal no utiliza existencias de otra: el traslado descuenta en origen al enviarse y suma en destino únicamente al confirmarse. Cambiar de sucursal no mueve unidades.

## Atomicidad y reintentos

### Caja de ventas

La vista Ventas muestra el buscador y el detalle sin abrir un formulario adicional. Código + Enter agrega una unidad y cantidad*código + Enter agrega varias; las lecturas quedan en una cola para conservar pulsaciones rápidas. La búsqueda exacta usa codigo en /api/catalogos/productos, filtrado antes del límite de 500 resultados. No se convierten códigos de barras a números. Cantidades inválidas, productos inactivos/inexistentes o stock insuficiente generan aviso y no agregan una línea.

Cliente (NIT) usa /api/catalogos/clientes?nit=... para coincidencia exacta normalizada. Registrar un cliente nuevo sigue exigiendo el módulo Clientes. El botón Guardar (facturar) abre el cobro y la confirmación del cobro abre el PIN. La caja envía total_esperado_centavos; el servidor recalcula y rechaza con 409 si no coincide, antes de escribir documento, correlativo o kardex. Los clientes anteriores pueden omitir ese campo.

El borrador por usuario/sucursal vive solo en memoria. En una respuesta incierta se conserva la misma solicitud; Verificar venta registrada consulta /api/ventas?operacion_id=... dentro del alcance permitido, incluso si el documento ya no está entre los 200 más recientes. Un reintento devuelve la venta original sin otro descuento. PIN, contraseñas y tokens no se almacenan en Web Storage.

El documento, su número, todas las existencias y todos los movimientos se guardan en una única transacción. Si una línea no alcanza o falla el kardex, se revierte todo. Los conflictos transitorios y las colisiones de primera creación se reintentan de forma completa; la actualización condicional de estado impide confirmar/anular dos veces. Los índices únicos impiden duplicar saldos o identificadores.

Repetir un registro debe utilizar el mismo operacion_id y los mismos datos: se devuelve el documento ya registrado sin volver a aplicar movimientos, incluso si cambió después el precio del catálogo. Otro contenido o usuario con esa clave obtiene 409. La interfaz conserva la solicitud mientras reintenta tras un error de red/servidor y bloquea su edición si la respuesta es incierta; también permite revisar el historial. El PIN solo permanece en memoria del formulario y se vuelve a validar en el servidor. No cambie la clave para reintentar una respuesta perdida.

El límite del código es de cinco solicitudes por usuario/IP por minuto. Autenticación y permisos se ejecutan antes del limitador; la actividad de otro cajero no consume su cupo.

## Verificación

La suite del backend cubre entradas/salidas, rollback por stock insuficiente y fallo del kardex, totales/cambio, permisos, referencias inexistentes, anulaciones y procesos PHP simultáneos contra una BD MongoDB temporal. Edge/CDP verifica los formularios, dos sucursales, comprobantes, móvil y la pérdida real de la respuesta HTTP después de confirmar un ingreso. Las tres colecciones Postman se ejecutan con Newman en la misma infraestructura temporal, sin escribir datos de prueba en la BD local del usuario.

Para ejecutar todo (con el navegador CDP ya abierto), desde frontend/:

```powershell
python tests/run-browser.py auth.browser.mjs catalogos.browser.mjs operaciones.browser.mjs auth.postman_collection.json catalogos.postman_collection.json inventario.postman_collection.json
```

Los datos y evidencias temporales quedan en test-results/ (ignorado); el ejecutor detiene sus propios servidores y elimina exclusivamente la BD temporal. MEMORY.md registra los resultados efectivamente obtenidos.
