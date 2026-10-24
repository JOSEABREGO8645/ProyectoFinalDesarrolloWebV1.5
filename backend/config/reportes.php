<?php

$columnas = static fn (...$campos) => array_map(static function ($campo) {
    [$key, $label, $tipo] = array_pad(explode('|', $campo), 3, 'texto');

    return compact('key', 'label', 'tipo');
}, $campos);

return [
    'existencias' => ['titulo' => 'Existencias de productos', 'descripcion' => 'Saldo actual por producto; incluye productos sin existencias.', 'fechas' => false, 'filtros' => ['q', 'categoria', 'proveedor_id', 'producto_id'],
        'columnas' => $columnas('codigo|Código', 'nombre|Producto', 'categoria|Categoría', 'cantidad|Existencias|entero', 'minimo|Mínimo|entero', 'estado|Estado')],
    'valoracion' => ['titulo' => 'Valoración del inventario por categoría', 'descripcion' => 'Costo, valor de venta y margen potencial del inventario actual a precios vigentes.', 'fechas' => false, 'filtros' => ['q', 'categoria', 'proveedor_id', 'producto_id'],
        'columnas' => $columnas('categoria|Categoría', 'productos|Productos|entero', 'cantidad|Unidades|entero', 'costo|Valor al costo|dinero', 'venta|Valor de venta|dinero', 'margen|Margen potencial|dinero')],
    'kardex' => ['titulo' => 'Kardex de movimientos', 'descripcion' => 'Entradas, salidas y saldos auditados durante el período.', 'fechas' => true, 'filtros' => ['producto_id'],
        'columnas' => $columnas('fecha|Fecha|fecha', 'codigo|Código', 'nombre|Producto', 'sucursal|Sucursal', 'movimiento|Movimiento', 'numero|Documento|entero', 'cantidad|Cantidad|entero', 'antes|Antes|entero', 'despues|Después|entero', 'responsable|Responsable')],
    'ventas-diarias' => ['titulo' => 'Ventas por día', 'descripcion' => 'Ventas emitidas, unidades, importe y ticket promedio por día; excluye anuladas.', 'fechas' => true, 'filtros' => [],
        'columnas' => $columnas('dia|Día', 'documentos|Ventas|entero', 'cantidad|Unidades|entero', 'total|Importe|dinero', 'promedio|Ticket promedio|dinero')],
    'productos-vendidos' => ['titulo' => 'Productos más vendidos', 'descripcion' => 'Ranking de productos por unidades vendidas e ingresos; excluye ventas anuladas.', 'fechas' => true, 'filtros' => ['producto_id'],
        'columnas' => $columnas('codigo|Código', 'nombre|Producto', 'cantidad|Unidades|entero', 'total|Importe vendido|dinero')],
    'ventas-clientes' => ['titulo' => 'Ventas por cliente', 'descripcion' => 'Frecuencia, unidades, importe y ticket promedio por cliente, incluyendo Consumidor Final.', 'fechas' => true, 'filtros' => ['cliente_id'],
        'columnas' => $columnas('nombre|Cliente', 'nit|NIT', 'documentos|Ventas|entero', 'cantidad|Unidades|entero', 'total|Importe|dinero', 'promedio|Ticket promedio|dinero')],
    'ingresos-proveedores' => ['titulo' => 'Ingresos por proveedor', 'descripcion' => 'Ingresos registrados, unidades recibidas y costo por proveedor; excluye anulados.', 'fechas' => true, 'filtros' => ['proveedor_id'],
        'columnas' => $columnas('nombre|Proveedor', 'documentos|Ingresos|entero', 'cantidad|Unidades|entero', 'total|Costo recibido|dinero')],
    'flujo-traslados' => ['titulo' => 'Flujo de traslados entre sucursales', 'descripcion' => 'Documentos y unidades agrupados por origen, destino y estado del traslado.', 'fechas' => true, 'filtros' => ['estado'],
        'columnas' => $columnas('origen|Origen', 'destino|Destino', 'estado|Estado', 'documentos|Traslados|entero', 'cantidad|Unidades del detalle|entero')],
    'anulaciones' => ['titulo' => 'Auditoría de anulaciones', 'descripcion' => 'Ventas, ingresos y traslados anulados durante el período, según fecha de anulación.', 'fechas' => true, 'filtros' => [],
        'columnas' => $columnas('fecha|Fecha de anulación|fecha', 'documento|Tipo', 'numero|Número|entero', 'sucursal|Sucursal / recorrido', 'responsable|Anulado por', 'motivo|Motivo', 'total|Importe original|dinero')],
];
