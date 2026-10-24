# Inicio — dashboard de la librería

## Acceso y datos

GET /api/dashboard exige JWT y alguno de estos módulos: catalogo, ventas, ingresos o traslados. Dashboard aparece automáticamente en la navegación al disponer de alguno de ellos; no es un módulo asignable nuevo. Inicio es la pantalla de bienvenida para todos los usuarios y se abre después de cada login correcto; el dashboard se abre desde su apartado independiente. El servicio comprueba cada permiso antes de consultar su indicador y omite completamente los indicadores no autorizados. Un empleado solo consulta su sucursal; el superusuario puede elegir una o consultar todas. Una sucursal histórica inactiva se puede consultar; una referencia inexistente devuelve 404.

La arquitectura mantiene Controller delgado, DashboardRequest, DashboardService y DashboardRepository. Las agregaciones usan directamente las colecciones MongoDB; no utilizan los listados de 200/500 registros de los CRUD para calcular totales. La migración agregar_indices_dashboard añade índices de fechas/sucursal en documentos y de estado en traslados. No hay escrituras de inventario desde el dashboard.

## Indicadores

| Permiso | Resumen |
| --- | --- |
| Catálogo | Productos activos/inactivos, unidades actuales, agotados y existencia baja; hasta diez alertas ordenadas por menor cantidad/nombre |
| Ventas | Número/importe/unidades de ventas emitidas del período; anuladas por separado, cinco documentos recientes y serie diaria con días vacíos en cero |
| Ingresos | Número/importe/unidades de ingresos registrados del período, anulados por separado y cinco documentos recientes |
| Traslados | Confirmados del período y cinco documentos recientes; documentos pendientes actuales y unidades en tránsito, incluyendo envíos anteriores al período |

Los importes son centavos enteros. Las ventas e ingresos anulados se excluyen de los totales monetarios. Los cinco documentos recientes incluyen su estado actual y pueden contener anulados. Las unidades físicas incluyen productos inactivos; las alertas se calculan solo sobre activos. Un producto sin documento de existencia cuenta como cero. Agotados y bajos son categorías separadas: cero o menos se considera agotado; cantidades positivas hasta stock_minimo son bajas. En la consulta global se suman las existencias de todas las sucursales antes de comparar el mínimo.

Los filtros desde/hasta afectan documentos y gráfica, mientras inventario y pendientes representan el estado actual. Las consultas del resumen son de lectura; no constituyen un cierre contable ni una instantánea transaccional de todos los indicadores. Los enlaces abren el módulo correspondiente con sus filtros habituales.

## Fechas y uso

desde y hasta tienen formato YYYY-MM-DD, deben enviarse juntos y admiten hasta 90 días inclusivos. El valor predeterminado es hoy y los seis días anteriores. Las fechas se interpretan en America/Guatemala (config/dashboard.php) y se consultan como límites UTC, incluyendo el día final completo. Los accesos de siete/treinta días usan la fecha final seleccionada. El botón Consultar actualiza los datos y muestra la hora de consulta.

La gráfica permite seleccionar un día con ratón o teclado y muestra su importe/número de ventas. Cuando no hay ventas se indica expresamente, sin valores de ejemplo. Hay recuperación ante fallos de red y presentación móvil con desplazamiento interno de la gráfica cuando el período es amplio.

## Verificación reproducible

Desde backend/, `php artisan test --compact tests/Feature/Dashboard/DashboardTest.php` usa bases MongoDB temporales para comprobar ceros, saldos por sucursal, productos inactivos, ventas/anulaciones, sumas mayores que el listado reciente, medianoche de Guatemala, períodos, permisos y sucursales inexistentes.

Con el perfil Edge/Chrome CDP exclusivo en 9222, desde frontend/:

```powershell
python tests/run-browser.py dashboard.browser.mjs dashboard.postman_collection.json
```

El ejecutor prepara fixtures únicamente en una BD con prefijo de pruebas, registra ingreso/venta/traslado mediante OperacionService y comprueba cinco unidades disponibles, Q 3.03 vendidos, Q 4.70 ingresados y un envío de dos unidades pendiente. Prueba además cajero con permiso solo de ventas y usuario con permiso solo de clientes. Newman ejecuta 16 solicitudes y el navegador comprueba gráfica, fechas/sucursales, red, permisos y móvil. No usar estos fixtures contra una BD real; el ejecutor valida el nombre, detiene sus servidores y elimina únicamente la base temporal.
