<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Repositories\PublicacionDatosRepository;
use MongoDB\Driver\Session;

class PublicacionDatosService
{
    public const COLECCIONES = ['sucursals', 'proveedores', 'clientes', 'productos', 'usuarios', 'ingresos', 'ventas', 'traslados', 'existencias', 'movimientos', 'contadores'];

    public function __construct(private PublicacionDatosRepository $repository) {}

    private function normalizar(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        if (! array_is_list($valor)) {
            ksort($valor);
        }
        foreach ($valor as &$dato) {
            $dato = $this->normalizar($dato);
        }

        return $valor;
    }

    private function huellaDocumento($documento): string
    {
        return hash('sha256', json_encode($this->normalizar(json_decode($documento->toCanonicalExtendedJSON(), true)), JSON_THROW_ON_ERROR));
    }

    public function huella(array $datos): string
    {
        $resumen = [];
        foreach ($datos as $espacio => $colecciones) {
            foreach ($colecciones as $coleccion => $documentos) {
                $resumen[$espacio][$coleccion] = [];
                foreach ($documentos as $documento) {
                    $resumen[$espacio][$coleccion][(string) $documento['_id']] = $this->huellaDocumento($documento);
                }
            }
        }

        return hash('sha256', json_encode($this->normalizar($resumen), JSON_THROW_ON_ERROR));
    }

    public function preparar(): array
    {
        $origen = $this->repository->leerOrigen(self::COLECCIONES);
        $destino = $this->repository->leerDestino($origen);
        $altas = [];
        $resumen = [];
        $claves = ['sucursals' => 'nombre', 'proveedores' => 'nombre', 'clientes' => 'nit', 'productos' => 'codigo', 'usuarios' => 'usuario'];
        foreach ($origen as $espacio => $colecciones) {
            foreach ($colecciones as $coleccion => $documentos) {
                $existentes = [];
                $naturales = [];
                foreach ($destino[$espacio][$coleccion] as $documento) {
                    $existentes[(string) $documento['_id']] = $documento;
                    if ($espacio === 'erp' && isset($claves[$coleccion])) {
                        $naturales[mb_strtolower(trim((string) $documento[$claves[$coleccion]]))] = (string) $documento['_id'];
                    }
                }
                $altas[$espacio][$coleccion] = [];
                foreach ($documentos as $documento) {
                    $id = (string) $documento['_id'];
                    if (isset($existentes[$id])) {
                        if ($this->huellaDocumento($existentes[$id]) !== $this->huellaDocumento($documento)) {
                            throw new ReglaNegocioException("Conflicto de contenido en {$espacio}.{$coleccion}; no se sobrescriben documentos.", 409);
                        }

                        continue;
                    }
                    if ($espacio === 'erp' && isset($claves[$coleccion])) {
                        $clave = mb_strtolower(trim((string) $documento[$claves[$coleccion]]));
                        if (isset($naturales[$clave])) {
                            throw new ReglaNegocioException("Conflicto de clave en {$coleccion}.{$claves[$coleccion]}.", 409);
                        }
                        $naturales[$clave] = $id;
                    }
                    if ($espacio === 'erp' && $coleccion === 'existencias' && (int) (string) $documento['cantidad'] < 0) {
                        throw new ReglaNegocioException('El origen contiene una existencia negativa.');
                    }
                    $altas[$espacio][$coleccion][] = $documento;
                }
                $resumen[$espacio.'.'.$coleccion] = ['origen' => count($documentos), 'destino' => count($destino[$espacio][$coleccion]), 'agregar' => count($altas[$espacio][$coleccion])];
            }
        }
        $this->validarReferencias($origen['erp'], $destino['erp']);

        return compact('origen', 'destino', 'altas', 'resumen');
    }

    private function validarReferencias(array $origen, array $destino): void
    {
        $ids = [];
        foreach (self::COLECCIONES as $coleccion) {
            foreach (array_merge($origen[$coleccion], $destino[$coleccion]) as $documento) {
                $ids[$coleccion][(string) $documento['_id']] = true;
            }
        }
        $reglas = ['usuarios' => ['sucursal_id' => 'sucursals'], 'productos' => ['proveedor_id' => 'proveedores'],
            'existencias' => ['producto_id' => 'productos', 'sucursal_id' => 'sucursals'],
            'ingresos' => ['proveedor_id' => 'proveedores', 'sucursal_id' => 'sucursals', 'usuario_id' => 'usuarios'],
            'ventas' => ['cliente_id' => 'clientes', 'sucursal_id' => 'sucursals', 'usuario_id' => 'usuarios'],
            'traslados' => ['sucursal_id' => 'sucursals', 'origen_id' => 'sucursals', 'destino_id' => 'sucursals', 'usuario_id' => 'usuarios'],
            'movimientos' => ['producto_id' => 'productos', 'sucursal_id' => 'sucursals', 'usuario_id' => 'usuarios']];
        foreach ($origen as $coleccion => $documentos) {
            foreach ($documentos as $documento) {
                $campos = array_merge($reglas[$coleccion] ?? [], ['anulado_por_id' => 'usuarios', 'confirmado_por_id' => 'usuarios']);
                foreach ($campos as $campo => $referencia) {
                    if ($documento->has($campo) && $documento[$campo] !== null && (string) $documento[$campo] !== '' && ! isset($ids[$referencia][(string) $documento[$campo]])) {
                        throw new ReglaNegocioException("Referencia incompleta en {$coleccion}.{$campo}.");
                    }
                }
                if ($coleccion === 'usuarios' && $documento->has('sucursal_ids')) {
                    foreach ($documento['sucursal_ids'] as $id) {
                        if (! isset($ids['sucursals'][(string) $id])) {
                            throw new ReglaNegocioException('Una sucursal asignada no existe.');
                        }
                    }
                }
            }
        }
    }

    public function aplicar(array $plan, string $carpeta): array
    {
        if (! is_dir($carpeta) && ! mkdir($carpeta, 0700, true)) {
            throw new \RuntimeException('No se pudo preparar el respaldo privado.');
        }
        foreach (['origen', 'destino'] as $lado) {
            foreach ($plan[$lado] as $espacio => $colecciones) {
                foreach ($colecciones as $coleccion => $documentos) {
                    $archivo = fopen($carpeta.'/'.$lado.'-'.$espacio.'-'.$coleccion.'.jsonl', 'x');
                    if ($archivo === false) {
                        throw new \RuntimeException('No se pudo crear un archivo de respaldo.');
                    }
                    try {
                        foreach ($documentos as $documento) {
                            $linea = $documento->toCanonicalExtendedJSON()."\n";
                            if (fwrite($archivo, $linea) !== strlen($linea)) {
                                throw new \RuntimeException('El respaldo no se pudo completar.');
                            }
                        }
                    } finally {
                        fclose($archivo);
                    }
                }
            }
        }
        if (file_put_contents($carpeta.'/manifest.json', json_encode(['resumen' => $plan['resumen'], 'origen_sha256' => $this->huella($plan['origen']), 'destino_sha256' => $this->huella($plan['destino'])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new \RuntimeException('No se pudo completar el manifiesto de respaldo.');
        }

        return $this->repository->transaccion(function (Session $sesion) use ($plan): array {
            if ($this->huella($this->repository->leerOrigen(self::COLECCIONES)) !== $this->huella($plan['origen'])
                || $this->huella($this->repository->leerDestino($plan['origen'], $sesion)) !== $this->huella($plan['destino'])) {
                throw new ReglaNegocioException('Los datos cambiaron desde la comprobación; repita la importación.', 409);
            }
            $this->repository->insertar($plan['altas'], $sesion);

            return $plan['resumen'];
        });
    }
}
