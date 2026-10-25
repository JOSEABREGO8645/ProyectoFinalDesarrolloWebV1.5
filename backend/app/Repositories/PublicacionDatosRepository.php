<?php

namespace App\Repositories;

use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;

use function MongoDB\with_transaction;

/** Transferencia administrativa de documentos existentes, sin recalcular inventario. */
class PublicacionDatosRepository
{
    public function __construct(private Database $origen, private array $destinos, private array $adicionales = []) {}

    public static function idsDocumentos(Database $base, string $coleccion): array
    {
        $ids = [];
        foreach ($base->selectCollection($coleccion)->find([], ['projection' => ['_id' => 1]]) as $documento) {
            $ids[(string) $documento['_id']] = true;
        }

        return $ids;
    }

    public function leerOrigen(array $colecciones): array
    {
        $datos = ['erp' => []];
        foreach ($colecciones as $coleccion) {
            $datos['erp'][$coleccion] = $this->origen->selectCollection($coleccion)->find([], ['typeMap' => ['root' => 'bson']])->toArray();
        }

        return array_merge($datos, $this->adicionales);
    }

    public function leerDestino(array $estructura, ?Session $sesion = null): array
    {
        $datos = [];
        foreach ($estructura as $espacio => $colecciones) {
            foreach ($colecciones as $coleccion => $documentos) {
                $datos[$espacio][$coleccion] = $this->destinos[$espacio]->selectCollection($coleccion)
                    ->find([], array_filter(['typeMap' => ['root' => 'bson'], 'session' => $sesion]))->toArray();
            }
        }

        return $datos;
    }

    public function transaccion(callable $accion): mixed
    {
        $sesion = $this->destinos['erp']->getManager()->startSession();
        $resultado = null;
        try {
            with_transaction($sesion, function (Session $sesion) use ($accion, &$resultado): void {
                $resultado = $accion($sesion);
            }, ['readConcern' => new ReadConcern('snapshot'), 'writeConcern' => new WriteConcern('majority')]);
        } finally {
            $sesion->endSession();
        }

        return $resultado;
    }

    public function insertar(array $altas, Session $sesion): void
    {
        foreach ($altas as $espacio => $colecciones) {
            foreach ($colecciones as $coleccion => $documentos) {
                if ($documentos !== []) {
                    $this->destinos[$espacio]->selectCollection($coleccion)->insertMany($documentos, ['session' => $sesion]);
                }
            }
        }
    }

    public function actualizarEtiquetas(array $cambios, Session $sesion): void
    {
        foreach ($cambios as $coleccion => $documentos) {
            foreach ($documentos as $documento) {
                $this->destinos['erp']->selectCollection($coleccion)->replaceOne(['_id' => $documento['_id']], $documento, ['session' => $sesion]);
            }
        }
    }
}
