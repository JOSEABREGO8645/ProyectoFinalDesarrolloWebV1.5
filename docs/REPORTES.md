# Fase 12 — nueve reportes

Los reportes son análisis diferentes por datos, agrupación o propósito; no se cuentan variantes de un mismo filtro como reportes adicionales. Todos exigen sesión JWT y el módulo reportes. Un empleado solo accede a su sucursal; el superusuario puede elegir una o sumar todas. Ese permiso autoriza los informes financieros de su sucursal, incluyendo la valoración del inventario. No requiere permisos de edición de los catálogos ni de los procesos.

## Informes y cálculos

| Reporte | Fuente y propósito | Filtros adicionales |
| --- | --- | --- |
| Existencias | Unidades actuales por producto, mínimo y disponibilidad; incluye inactivos y saldos cero | Código/nombre literal, categoría, proveedor, producto |
| Valoración | Agrupa inventario por categoría y calcula costo, valor de venta y margen potencial con precios vigentes | Código/nombre, categoría, proveedor, producto |
| Kardex | Movimientos cronológicos con cantidad firmada, saldo antes/después, documento y responsable | Producto |
| Ventas diarias | Ventas emitidas por día con documentos, unidades, importe y ticket promedio | Período |
| Productos vendidos | Ranking de unidades e importe por producto, usando snapshots de líneas de ventas emitidas | Producto |
| Ventas por cliente | Frecuencia, unidades, importe y ticket promedio por cliente; incluye CF | Cliente registrado |
| Ingresos por proveedor | Ingresos registrados, unidades y costo agrupados por proveedor | Proveedor |
| Flujo de traslados | Agrupa documentos y unidades por origen, destino y estado | Estado |
| Anulaciones | Une los tres procesos y muestra fecha de anulación, responsable, motivo e importe original | Período de anulación |

Estos grupos permiten distribuir tres reportes de inventario, tres de ventas y tres de control/abastecimiento entre los integrantes. La matriz con nombres se completa en la documentación final.

Todos admiten sucursal. Los dos informes de inventario representan el saldo actual y no admiten fechas; los demás admiten desde/hasta inclusivos en America/Guatemala, hasta 366 días, predeterminados a los últimos treinta. La auditoría filtra por anulado_at, no por la fecha original de creación. Los demás filtran por created_at. En traslados un empleado ve los recorridos donde su sucursal es origen o destino.

Las ventas e ingresos anulados se excluyen de sus informes de operación. El flujo muestra sus estados por separado y suma unidades confirmadas y pendientes, sin convertir las anuladas en unidades recibidas. La auditoría no mezcla costos de ingresos con importes de ventas en una suma monetaria común; informa cantidades de anulaciones por proceso. El importe de un traslado se deja vacío porque no es una venta o compra.

La valoración suma productos activos e inactivos que tengan stock. Usa precios actuales y expresa un margen potencial, no utilidad histórica. Los importes y promedios se calculan con Decimal128/BigDecimal y se devuelven como cadenas con dos decimales. El frontend conserva esa precisión al formatear, incluso por encima del rango exacto de Number. El ticket promedio se redondea a dos decimales, mitad hacia arriba; los promedios de filas no se suman como totales.

El kardex informa entradas, salidas y variación de las filas del período; no presenta esa suma como saldo final conjunto de diferentes productos. Sus saldos antes/después pertenecen al movimiento y a la sucursal de cada línea.

## API, paginación y salida

- GET /api/reportes devuelve nueve definiciones, columnas, filtros y fechas predeterminadas.
- GET /api/reportes/productos?q=... ofrece hasta 500 coincidencias mínimas, incluyendo inactivos, para filtrar historial.
- GET /api/reportes/{tipo} devuelve filas, resumen, filtros aplicados, fecha de generación y paginación.
- El parámetro formato=csv exporta todas las filas coincidentes en un stream, independientemente de la página solicitada.

Los tipos son existencias, valoracion, kardex, ventas-diarias, productos-vendidos, ventas-clientes, ingresos-proveedores, flujo-traslados y anulaciones. Los filtros de IDs deben corresponder a documentos existentes; una referencia inexistente devuelve 404 y los filtros incompatibles con el reporte, 422. La paginación admite de 1 a 100 filas por página y los totales se calculan sobre todas las coincidencias, sin el límite de los CRUD.

Consultar aplica los filtros. Cambiar campos sin consultar no altera el informe generado: exportación y paginación utilizan sus filtros aplicados. Imprimir página abre una vista que identifica sucursal, período, filtros, página y totales del reporte completo. La impresión usa el diálogo del navegador; se desactivan animaciones para evitar páginas en blanco.

El CSV utiliza UTF-8 con BOM, comas y campos escapados. Los textos que pueden interpretarse como fórmulas se prefijan con apóstrofo; las cantidades negativas legítimas se mantienen numéricas. Los códigos conservan su contenido en el CSV; al abrirlo en Excel, importe la columna Código como texto para que Excel no quite ceros iniciales. La exportación utiliza Axios con JWT en cabecera y no incorpora tokens en URLs. JSON y CSV llevan Cache-Control privado/no-store.

Los informes y las exportaciones son consultas actuales, no cierres contables ni snapshots bloqueados; operaciones nuevas pueden cambiar una consulta posterior. No realizan escrituras de inventario.

## Verificación

Desde backend/:

```powershell
php artisan route:list --path=api
php artisan test --compact tests/Feature/Reportes/ReportesTest.php
```

Con el perfil CDP exclusivo abierto, desde frontend/:

```powershell
python tests/run-browser.py reportes.browser.mjs reportes.postman_collection.json
```

Los fixtures protegidos preparan productos y operaciones ficticios únicamente en una base temporal. Prueban además un analista con solo Reportes y un cajero sin ese permiso. El navegador verifica la nueva bienvenida, orden de Ventas, nueve informes, filtros, CSV completo pese a paginar, PDF de impresión, protección de fórmulas, precisión monetaria, fallos de red y móvil. Newman ejecuta casos válidos, inválidos, sin sesión/permiso y referencias inexistentes. El ejecutor elimina exclusivamente la BD temporal; la limpieza de caché entre suites comprueba tanto el nombre de BD como su ruta dentro de test-results/.
