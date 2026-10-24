<?php

namespace App\Repositories;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;

class DashboardRepository
{
    public function inventario(?string $sucursal): iterable
    {
        $filtro = ['$expr' => ['$eq' => ['$producto_id', '$$producto']]];
        if ($sucursal !== null) {
            $filtro['sucursal_id'] = $sucursal;
        }

        return Producto::raw(fn ($coleccion) => $coleccion->aggregate([
            ['$lookup' => ['from' => 'existencias', 'let' => ['producto' => ['$toString' => '$_id']],
                'pipeline' => [['$match' => $filtro], ['$group' => ['_id' => null, 'cantidad' => ['$sum' => '$cantidad']]]], 'as' => 'saldos']],
            ['$project' => ['_id' => ['$toString' => '$_id'], 'codigo' => 1, 'nombre' => 1, 'activo' => 1,
                'stock_minimo' => ['$ifNull' => ['$stock_minimo', 0]],
                'existencia' => ['$ifNull' => [['$arrayElemAt' => ['$saldos.cantidad', 0]], 0]]]],
        ]));
    }

    public function documentos(string $tipo, ?string $sucursal, UTCDateTime $desde, UTCDateTime $hasta, string $zona): array
    {
        $match = ['created_at' => ['$gte' => $desde, '$lt' => $hasta]];
        if ($sucursal !== null) {
            if ($tipo === 'traslados') {
                $match['$or'] = [['origen_id' => $sucursal], ['destino_id' => $sucursal]];
            } else {
                $match['sucursal_id'] = $sucursal;
            }
        }
        $filas = DB::connection('mongodb')->getCollection($tipo)->aggregate([
            ['$match' => $match],
            ['$facet' => [
                'estados' => [['$group' => ['_id' => '$estado', 'cantidad' => ['$sum' => 1], 'centavos' => ['$sum' => '$total_centavos'], 'unidades' => ['$sum' => ['$sum' => '$items.cantidad']]]]],
                'dias' => [['$group' => ['_id' => ['fecha' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at', 'timezone' => $zona]], 'estado' => '$estado'], 'centavos' => ['$sum' => '$total_centavos'], 'cantidad' => ['$sum' => 1]]]],
                'recientes' => [['$sort' => ['created_at' => -1, '_id' => -1]], ['$limit' => 5], ['$project' => [
                    '_id' => ['$toString' => '$_id'], 'numero' => 1, 'estado' => 1, 'sucursal_nombre' => 1, 'cliente_nombre' => 1,
                    'proveedor_nombre' => 1, 'origen_nombre' => 1, 'destino_nombre' => 1, 'total_centavos' => 1,
                    'fecha' => ['$dateToString' => ['format' => '%Y-%m-%dT%H:%M:%S.%LZ', 'date' => '$created_at', 'timezone' => 'UTC']]]]],
            ]],
        ], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();

        return $filas[0];
    }

    public function pendientes(?string $sucursal): array
    {
        $match = ['estado' => 'enviado'];
        if ($sucursal !== null) {
            $match['$or'] = [['origen_id' => $sucursal], ['destino_id' => $sucursal]];
        }
        $filas = DB::connection('mongodb')->getCollection('traslados')->aggregate([
            ['$match' => $match], ['$group' => ['_id' => null, 'documentos' => ['$sum' => 1], 'unidades' => ['$sum' => ['$sum' => '$items.cantidad']]]],
        ])->toArray();

        return ['documentos' => (int) ($filas[0]['documentos'] ?? 0), 'unidades' => (int) ($filas[0]['unidades'] ?? 0)];
    }
}
