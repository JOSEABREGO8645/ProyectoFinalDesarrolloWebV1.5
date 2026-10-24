# Colecciones comprobadas hasta la fase 10

| Modelo | Colección efectiva | Referencias |
| --- | --- | --- |
| Sucursal | sucursals | Conservada para mantener los datos existentes |
| Proveedor | proveedores | Referenciada por productos e ingresos |
| Cliente | clientes | Referenciada por ventas |
| Producto | productos | proveedor_id opcional, string del _id de Proveedor |
| Existencia | existencias | producto_id y sucursal_id como strings; cantidad entera |
| Usuario | usuarios | sucursal_id string; rol y módulos; hashes ocultos; versión de seguridad entera |
| Sesion | auth_sessions | usuario_id string; hash de refresh, vencimiento y revocación; versión de seguridad de la cuenta |
| Ingreso | ingresos | sucursal_id, proveedor_id, usuario_id e items.producto_id como strings; estado registrado/anulado |
| Venta | ventas | sucursal_id, cliente_id opcional, usuario_id e items.producto_id como strings; estado emitida/anulado |
| Traslado | traslados | sucursal_id = origen_id, destino_id, usuario_id e items.producto_id como strings; enviado/confirmado/anulado |
| Movimiento | movimientos | documento_id, producto_id, sucursal_id y usuario_id como strings; cantidad con signo y saldos antes/después |
| — (Repository) | contadores | _id textual tipo:sucursal_id y valor entero, incrementado dentro de la transacción |

En mongodb/laravel-mongodb 5.11 se fija `$table` para determinar la colección. El `$collection = 'sucursales'` del modelo inicial no define su colección efectiva. La prueba de regresión verifica que Sucursales continúa usando sucursals; no se renombró ninguna colección ni se trasladaron documentos.

La API conserva el identificador id y agrega _id como alias para la interfaz. Los precios se guardan como BSON Decimal128 mediante los casts decimal:2 y se exponen con dos decimales. Las consultas de existencias se agrupan en bloque; no hay escrituras de inventario desde el CRUD de productos.

usuarios tiene un índice único en usuario. Las cuentas se normalizan a minúsculas. auth_sessions se genera mediante logins reales; no se cargan sesiones artificiales como datos de prueba. Los vencimientos de sesión y revocaciones se guardan como segundos Unix; contraseñas, código y hashes de refresh no se exponen en JSON.

Ingresos, ventas y traslados conservan un snapshot de códigos, nombres y cantidades de cada línea. En ingresos y ventas, precio_centavos/subtotal_centavos por línea y total_centavos por documento son enteros; efectivo_centavos/cambio_centavos se guardan en ventas. Los precios vigentes del catálogo se toman en el servidor. No se persiste el PIN. operacion_id es UUID único y huella (oculta en JSON) permite distinguir un reintento de un intento de reutilizar la clave con otro contenido o usuario.

La migración 2026_10_23_232450_crear_indices_inventario agrega unicidad producto_id+sucursal_id en existencias, operacion_id y sucursal_id+numero en cada colección documental, e índices sucursal_id+created_at/documento_id en movimientos. Los movimientos no se editan ni eliminan: las anulaciones agregan movimientos inversos. anulado_por_id y confirmado_por_id conservan la identidad del actor y bloquean su eliminación si tiene historial. La tabla técnica migrations registra la preparación del esquema en la conexión mongodb.
