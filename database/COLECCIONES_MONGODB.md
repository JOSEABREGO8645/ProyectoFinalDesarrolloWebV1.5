# Colecciones comprobadas hasta las fases 8 y 9

| Modelo | Colección efectiva | Referencias |
| --- | --- | --- |
| Sucursal | sucursals | Conservada para mantener los datos existentes |
| Proveedor | proveedores | Referenciada por productos e ingresos |
| Cliente | clientes | Referenciada por ventas |
| Producto | productos | proveedor_id opcional, string del _id de Proveedor |
| Existencia | existencias | producto_id y sucursal_id como strings; cantidad entera |
| Usuario | usuarios | sucursal_id string; rol y módulos; hashes ocultos; versión de seguridad entera |
| Sesion | auth_sessions | usuario_id string; hash de refresh, vencimiento y revocación; versión de seguridad de la cuenta |

En mongodb/laravel-mongodb 5.11 se fija `$table` para determinar la colección. El `$collection = 'sucursales'` del modelo inicial no define su colección efectiva. La prueba de regresión verifica que Sucursales continúa usando sucursals; no se renombró ninguna colección ni se trasladaron documentos.

La API conserva el identificador id y agrega _id como alias para la interfaz. Los precios se guardan como BSON Decimal128 mediante los casts decimal:2 y se exponen con dos decimales. Las consultas de existencias se agrupan en bloque; no hay escrituras de inventario desde el CRUD de productos.

usuarios tiene un índice único en usuario. Las cuentas se normalizan a minúsculas. auth_sessions se genera mediante logins reales; no se cargan sesiones artificiales como datos de prueba. Los vencimientos de sesión y revocaciones se guardan como segundos Unix; contraseñas, código y hashes de refresh no se exponen en JSON.

Las colecciones restantes se implementarán con los procesos integrados. Este documento no afirma que esos módulos estén creados.
