<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

class ReporteRepository
{
    private function agregar(string $coleccion, array $pipeline): iterable
    {
        return DB::connection('mongodb')->getCollection($coleccion)->aggregate($pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'], 'allowDiskUse' => true]);
    }

    public function inventario(array $f): iterable
    {
        $match = [];
        foreach (['categoria', 'proveedor_id'] as $campo) {
            if (isset($f[$campo]) && $f[$campo] !== '') {
                $match[$campo] = $f[$campo];
            }
        }
        if (! empty($f['producto_id'])) {
            $match['_id'] = new ObjectId($f['producto_id']);
        }
        if (isset($f['q']) && $f['q'] !== '') {
            $r = new Regex(preg_quote($f['q']), 'i');
            $match['$or'] = [['codigo' => $r], ['nombre' => $r]];
        }
        $saldo = ['$expr' => ['$eq' => ['$producto_id', '$$producto']]];
        if ($f['sucursal_id'] !== null) {
            $saldo['sucursal_id'] = $f['sucursal_id'];
        }

        return $this->agregar('productos', [
            ['$match' => (object) $match],
            ['$lookup' => ['from' => 'existencias', 'let' => ['producto' => ['$toString' => '$_id']], 'pipeline' => [
                ['$match' => $saldo], ['$group' => ['_id' => null, 'cantidad' => ['$sum' => '$cantidad']]],
            ], 'as' => 'saldos']],
            ['$project' => ['_id' => 0, 'codigo' => 1, 'nombre' => 1, 'categoria' => 1, 'activo' => 1, 'stock_minimo' => 1,
                'precio_costo' => ['$toString' => ['$ifNull' => ['$precio_costo', 0]]], 'precio_venta' => ['$toString' => ['$ifNull' => ['$precio_venta', 0]]],
                'cantidad' => ['$ifNull' => [['$arrayElemAt' => ['$saldos.cantidad', 0]], 0]]]],
            ['$sort' => ['codigo' => 1, 'nombre' => 1]],
        ]);
    }

    private function alcance(array $f, string $tipo, string $fecha = 'created_at'): array
    {
        $match = [$fecha => ['$gte' => $f['inicio_utc'], '$lt' => $f['fin_utc']]];
        if ($f['sucursal_id'] !== null) {
            if ($tipo === 'traslados') {
                $match['$or'] = [['origen_id' => $f['sucursal_id']], ['destino_id' => $f['sucursal_id']]];
            } else {
                $match['sucursal_id'] = $f['sucursal_id'];
            }
        }

        return $match;
    }

    public function kardex(array $f): iterable
    {
        $match = $this->alcance($f, 'movimientos');
        if (! empty($f['producto_id'])) {
            $match['producto_id'] = $f['producto_id'];
        }

        return $this->agregar('movimientos', [
            ['$match' => $match], ['$sort' => ['created_at' => 1, '_id' => 1]], ['$project' => ['_id' => 0,
                'fecha' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'UTC', 'format' => '%Y-%m-%dT%H:%M:%S.%LZ']],
                'codigo' => 1, 'nombre' => 1, 'sucursal_id' => 1, 'movimiento' => '$tipo', 'numero' => 1, 'cantidad' => 1, 'antes' => 1, 'despues' => 1, 'responsable' => '$usuario_nombre']],
        ]);
    }

    public function agrupado(string $tipo, array $f): iterable
    {
        $coleccion = $tipo === 'ingresos-proveedores' ? 'ingresos' : ($tipo === 'flujo-traslados' ? 'traslados' : 'ventas');
        $match = $this->alcance($f, $coleccion);
        if ($coleccion === 'ventas') {
            $match['estado'] = 'emitida';
        } elseif ($coleccion === 'ingresos') {
            $match['estado'] = 'registrado';
        } elseif (! empty($f['estado'])) {
            $match['estado'] = $f['estado'];
        }
        foreach (['cliente_id', 'proveedor_id'] as $campo) {
            if (isset($f[$campo]) && $f[$campo] !== '') {
                $match[$campo] = $f[$campo];
            }
        }
        $pipeline = [['$match' => $match], ['$sort' => ['created_at' => -1, '_id' => -1]]];
        $campos = ['documentos' => ['$sum' => 1], 'cantidad' => ['$sum' => ['$sum' => '$items.cantidad']], 'centavos' => ['$sum' => ['$toDecimal' => '$total_centavos']]];
        switch ($tipo) {
            case 'ventas-diarias':
                $grupo = ['_id' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d', 'timezone' => $f['zona']]]] + $campos;
                $proyeccion = ['_id' => 0, 'dia' => '$_id', 'documentos' => 1, 'cantidad' => 1, 'centavos' => 1];
                $orden = ['dia' => 1];
                break;
            case 'productos-vendidos':
                $pipeline[] = ['$unwind' => '$items'];
                if (! empty($f['producto_id'])) {
                    $pipeline[] = ['$match' => ['items.producto_id' => $f['producto_id']]];
                }
                $grupo = ['_id' => '$items.producto_id', 'codigo' => ['$first' => '$items.codigo'], 'nombre' => ['$first' => '$items.nombre'],
                    'cantidad' => ['$sum' => '$items.cantidad'], 'centavos' => ['$sum' => ['$toDecimal' => '$items.subtotal_centavos']]];
                $proyeccion = ['_id' => 0, 'codigo' => 1, 'nombre' => 1, 'cantidad' => 1, 'centavos' => 1];
                $orden = ['cantidad' => -1, 'centavos' => -1, 'codigo' => 1];
                break;
            case 'ventas-clientes':
                $grupo = ['_id' => ['$ifNull' => ['$cliente_id', 'CF']], 'nombre' => ['$first' => '$cliente_nombre'], 'nit' => ['$first' => '$cliente_nit']] + $campos;
                $proyeccion = ['_id' => 0, 'nombre' => 1, 'nit' => 1, 'documentos' => 1, 'cantidad' => 1, 'centavos' => 1];
                $orden = ['centavos' => -1, 'nombre' => 1];
                break;
            case 'ingresos-proveedores':
                $grupo = ['_id' => '$proveedor_id', 'nombre' => ['$first' => '$proveedor_nombre']] + $campos;
                $proyeccion = ['_id' => 0, 'nombre' => 1, 'documentos' => 1, 'cantidad' => 1, 'centavos' => 1];
                $orden = ['centavos' => -1, 'nombre' => 1];
                break;
            default:
                $grupo = ['_id' => ['origen' => '$origen_id', 'destino' => '$destino_id', 'estado' => '$estado'],
                    'origen' => ['$first' => '$origen_nombre'], 'destino' => ['$first' => '$destino_nombre'], 'estado' => ['$first' => '$estado'],
                    'documentos' => ['$sum' => 1], 'cantidad' => ['$sum' => ['$sum' => '$items.cantidad']]];
                $proyeccion = ['_id' => 0, 'origen' => 1, 'destino' => 1, 'estado' => 1, 'documentos' => 1, 'cantidad' => 1];
                $orden = ['origen' => 1, 'destino' => 1, 'estado' => 1];
        }

        return $this->agregar($coleccion, array_merge($pipeline, [['$group' => $grupo], ['$project' => $proyeccion], ['$sort' => $orden]]));
    }

    public function anulaciones(array $f): iterable
    {
        $pipeline = function (string $tipo) use ($f): array {
            $scope = $this->alcance($f, $tipo, 'fecha_anulacion');

            return [
                ['$match' => ['estado' => 'anulado']],
                ['$addFields' => ['fecha_anulacion' => ['$convert' => ['input' => '$anulado_at', 'to' => 'date', 'onError' => null, 'onNull' => null]]]],
                ['$match' => $scope],
                ['$project' => ['_id' => 1, 'fecha' => ['$dateToString' => ['date' => '$fecha_anulacion', 'timezone' => 'UTC', 'format' => '%Y-%m-%dT%H:%M:%S.%LZ']],
                    'documento' => ['$literal' => $tipo], 'numero' => 1, 'sucursal_id' => 1, 'sucursal_nombre' => 1, 'origen_nombre' => 1, 'destino_nombre' => 1,
                    'responsable' => '$anulado_por', 'motivo' => '$motivo_anulacion', 'centavos' => '$total_centavos']],
            ];
        };

        return $this->agregar('ventas', array_merge($pipeline('ventas'), [
            ['$unionWith' => ['coll' => 'ingresos', 'pipeline' => $pipeline('ingresos')]],
            ['$unionWith' => ['coll' => 'traslados', 'pipeline' => $pipeline('traslados')]], ['$sort' => ['fecha' => 1, 'documento' => 1, '_id' => 1]],
        ]));
    }
}
