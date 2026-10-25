# Publicación gratuita: Render + MongoDB Atlas

Estado: aplicación publicada en https://libreria-san-francisco.onrender.com y proveedores en https://libreria-facturacion-demo.onrender.com y https://libreria-pagos-demo.onrender.com. Verificados acceso/renovación HTTPS, permisos, tres procesos con kardex, nueve reportes JSON/CSV y las tres integraciones desde el hosting. Factura y pago conservan el comprobante original al repetir la petición HTTP directamente a los proveedores. A petición del usuario, se incorporó una copia de los datos locales a la base de Atlas conservando el conjunto de ejemplo previo. Evidencia de la verificación inicial en `docs/evidencias/publicacion-online.json`.

La imagen de publicación utiliza `VITE_DEMO=false`, por lo que omite el aviso superior. La demostración local separada mantiene su configuración propia. El nombre interno histórico de la base publicada continúa siendo `libreria_demo`; los registros importados conservan sus IDs y las existencias de sus sucursales originales.

Importación administrativa: `php docker/publicacion/importar_local.php` comprueba el plan sin escribir; `--aplicar` respalda documentos BSON de ambos lados y copia únicamente los nuevos en una transacción. Rechaza conflictos y cambios detectados entre la comprobación y la copia. Usa la conexión administrativa privada conservada durante la preparación y no cambia las credenciales limitadas de los servicios. Los respaldos y datos de acceso permanecen en `backend/storage/app/private/publicacion/`, excluidos de Git. La copia conserva inventario y kardex existentes; no genera ingresos o movimientos nuevos para representar otra vez las mismas unidades.

La imagen fue construida y probada localmente: 34 comprobaciones y una colección Postman de 29 solicitudes/48 aserciones sin fallos. El navegador accedió a Vue compilado y renovó su sesión; el reinicio conservó sesión y límite de intentos. Registro en `docs/evidencias/publicacion-local.json`. Estas pruebas usan HTTP de loopback y MongoDB local, y no acreditan una publicación en Render/Atlas.

## Archivos preparados

- `docker/publicacion/Dockerfile`: compila Vue y prepara PHP/Apache con Laravel y MongoDB; `/api` y la interfaz comparten origen.
- `render.yaml`: un servicio web gratuito para interfaz y API.
- `docker/publicacion/render-apis.yaml`: dos servicios Node gratuitos independientes, con historial en MongoDB.
- `docker/publicacion/preparar_atlas.py`: secretos y preparación de datos privados, sin cambiar los `.env` habituales ni cargar la base comercial.
- `CACHE_STORE=mongodb`: caché y limitadores persistidos; las sesiones JWT ya usan `auth_sessions`.

## 1. Crear cuentas y base

1. Crear una cuenta en [MongoDB Atlas](https://www.mongodb.com/cloud/atlas/register) y otra en [Render](https://dashboard.render.com/register). Se puede usar GitHub para la vinculación de Render con el repositorio.
2. En Atlas, crear un clúster **Free**, sin seleccionar Flex ni planes de pago. Revisar el importe antes de finalizar.
3. Crear usuarios de base distintos, con `readWrite` solo en su base: `libreria_demo`, `libreria_facturacion_demo` y `libreria_pagos_demo`.
4. Permitir temporalmente la IP del equipo para preparar los datos y añadir los rangos de salida que Render muestre para la región elegida. No abrir toda la red como configuración permanente.
5. Copiar las tres URI `mongodb+srv` desde Atlas. Codificar caracteres especiales de las contraseñas tal como indica el panel. No enviarlas al chat ni guardarlas en archivos públicos.

## 2. Preparar configuración privada y datos

Desde la raíz:

```powershell
python docker/publicacion/preparar_atlas.py --preparar
```

Abrir **localmente** `backend/storage/app/private/publicacion/config.json`. Los secretos se generan una vez y se conservan. Completar `DB_URI`, `API_FACTURACION_MONGO_URI` y `API_PAGOS_MONGO_URI` con las conexiones de sus usuarios de Atlas. No compartir el archivo.

```powershell
python docker/publicacion/preparar_atlas.py --cargar
```

Este paso requiere tener Composer/dependencias instalados. Solo admite una URI de Atlas, prepara `libreria_demo` mediante el servicio existente, y usa otro directorio privado de almacenamiento. No cambia la base local ni sus credenciales. Las guardas del preparador rechazan una base ocupada ajena a su demostración.

Los accesos de **esta** demostración publicada se guardan en `backend/storage/app/private/publicacion/storage/app/private/demo/libreria_demo/accesos.json`. Son distintos del archivo de demostración local. Conservarlos; no usar esa ruta como descarga pública.

## 3. Publicar las APIs independientes primero

1. Los cambios deben estar en el repositorio GitHub conectado. Crear un Blueprint en Render y seleccionar como ruta `docker/publicacion/render-apis.yaml`.
2. Para facturación, introducir `API_MONGO_URI` y `API_TOKEN` desde los campos privados `API_FACTURACION_MONGO_URI` y `FACTURACION_API_TOKEN`.
3. Para pagos, usar `API_PAGOS_MONGO_URI` y `PAGOS_API_TOKEN`. No intercambiar las credenciales.
4. Revisar que ambos servicios tienen plan **Free** y desplegar.
5. Abrir sus URLs reales `/salud`. Deben responder `ok: true`, el tipo correcto y `almacen: mongodb`.
6. Completar `FACTURACION_API_URL` y `PAGOS_API_URL` con las URLs base de los servicios, sin rutas: `https://libreria-facturacion-demo.onrender.com` y `https://libreria-pagos-demo.onrender.com`. ApiPruebaClient añade `/v1/facturas` o `/v1/comprobantes-pago`; incluir esas rutas en la variable las duplicaría.

## 4. Publicar la aplicación

```powershell
python docker/publicacion/preparar_atlas.py --exportar
```

El exportador deja tres archivos `.env` privados para copiar variables a los paneles de los servicios. No imprimirlos en una terminal grabada ni subirlos al repositorio.

Crear otro Blueprint con el `render.yaml` de la raíz e introducir las siete variables solicitadas desde `publicacion/backend.env`. La URL del servicio se toma de `RENDER_EXTERNAL_URL`; se usa también como origen autorizado. La cookie es Secure y la compilación muestra el aviso de datos ficticios. Seleccionar **Free**, sin disco de pago.

El arranque no siembra datos ni crea administradores: se prepararon en Atlas desde el paso anterior. No se despliega una herramienta pública para cargar o reiniciar bases.

## 5. Verificar antes de entregar

- La URL de inicio sirve Vue y `/up` responde correctamente.
- `/api/auth/me` sin sesión devuelve 401 y no el HTML del frontend.
- Iniciar sesión, recargar y renovar; cerrar sesión invalida la sesión.
- Comprobar los dos roles y cambios de sucursal; un empleado no accede a Usuarios.
- Completar ingreso, venta y traslado; revisar documentos y kardex por sucursal.
- Ejecutar las tres integraciones. Reiniciar cada proveedor y recuperar el mismo comprobante sin duplicación.
- Consultar y exportar los nueve reportes.
- Registrar URLs, fecha y resultados en `docs/evidencias/` y actualizar los PDF. Entregar los accesos al docente por canal privado.

## Límites del plan

Render Free puede apagarse por inactividad, comparte 750 horas mensuales entre servicios y no conserva archivos locales. Los tres servicios pueden tardar en arrancar; abrir `/salud` de ambos proveedores antes de la demostración y esperar que estén listos. No se elimina el tiempo de espera del backend para ocultar un arranque frío. Si la primera llamada falla, el documento permanece y puede reintentarse la integración con su misma clave. Atlas Free ofrece 0.5 GB; revisar cuotas y planes en los paneles. Los secretos y datos persistentes permanecen fuera del disco efímero de Render.

Referencias oficiales: [Render Free](https://render.com/docs/free), [Blueprints](https://render.com/docs/blueprint-spec), [Atlas Free](https://www.mongodb.com/docs/atlas/reference/free-shared-limitations/), [caché MongoDB en Laravel](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/v5.x/cache/).
