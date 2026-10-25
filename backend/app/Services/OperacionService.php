<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\DocumentoInventario;
use App\Models\Usuario;
use App\Repositories\ClienteRepository;
use App\Repositories\MovimientoRepository;
use App\Repositories\OperacionRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\SucursalRepository;
use Closure;
use MongoDB\Driver\Exception\BulkWriteException;

class OperacionService
{
    public function __construct(
        private OperacionRepository $repository,
        private InventarioService $inventario,
        private ProductoRepository $productos,
        private SucursalRepository $sucursales,
        private ProveedorRepository $proveedores,
        private ClienteRepository $clientes,
        private MovimientoRepository $movimientos,
        private AuthService $auth,
    ) {}

    public function filtrar(string $tipo, array $filtros, Usuario $usuario): array
    {
        return $this->repository->filtrar($tipo, $filtros, $this->sucursalConsulta($filtros, $usuario));
    }

    public function kardex(array $filtros, Usuario $usuario): array
    {
        return $this->movimientos->filtrar($filtros, $this->sucursalConsulta($filtros, $usuario));
    }

    public function buscar(string $tipo, string $id, Usuario $usuario): DocumentoInventario
    {
        $documento = $this->repository->buscar($tipo, $id);
        if ($usuario->rol !== 'superusuario') {
            $sucursales = $tipo === 'traslados' ? [$documento->origen_id, $documento->destino_id] : [$documento->sucursal_id];
            abort_unless(in_array($usuario->sucursal_id, $sucursales, true), 404);
        }

        return $documento;
    }

    public function crear(string $tipo, array $datos, Usuario $usuario): DocumentoInventario
    {
        $sucursalId = $datos['sucursal_id'] ?? $usuario->sucursal_id;
        if ($sucursalId !== $usuario->sucursal_id) {
            throw new ReglaNegocioException('Cambie a la sucursal correspondiente antes de registrar el documento.', 403);
        }
        if ($tipo === 'ventas') {
            $this->auth->verificarCodigo($usuario, $datos['codigo']);
        }
        unset($datos['codigo']); // El PIN nunca se guarda en documentos, huellas ni kardex.
        $datos['sucursal_id'] = $sucursalId;
        foreach ($datos['items'] as &$item) {
            $item['cantidad'] = (int) $item['cantidad'];
        }
        unset($item);
        $huella = hash('sha256', json_encode($this->ordenar($datos), JSON_THROW_ON_ERROR));

        return $this->atomica(function () use ($tipo, $datos, $usuario, $sucursalId, $huella) {
            $existente = $this->repository->porOperacion($tipo, $datos['operacion_id']);
            if ($existente) {
                if ($existente->usuario_id !== (string) $usuario->getKey() || ! hash_equals($existente->huella, $huella)) {
                    throw new ReglaNegocioException('Este identificador ya se utilizó en otra operación.', 409);
                }

                return $existente;
            }
            $sucursal = $this->sucursales->encontrarActiva($sucursalId);
            if (! $sucursal) {
                throw new ReglaNegocioException('Seleccione una sucursal activa.');
            }
            $extras = [];
            if ($tipo === 'ingresos') {
                $proveedor = $this->proveedores->buscarPorId($datos['proveedor_id']);
                if (! $proveedor->activo) {
                    throw new ReglaNegocioException('El proveedor está inactivo.');
                }
                $extras = ['proveedor_id' => (string) $proveedor->getKey(), 'proveedor_nombre' => $proveedor->nombre];
            } elseif ($tipo === 'ventas') {
                $cliente = ! empty($datos['cliente_id']) ? $this->clientes->buscarPorId($datos['cliente_id']) : null;
                $extras = ['cliente_id' => $cliente ? (string) $cliente->getKey() : null,
                    'cliente_nombre' => $cliente?->nombre ?? 'Consumidor Final', 'cliente_nit' => $cliente?->nit ?? 'CF'];
            } else {
                $destino = $this->sucursales->encontrarActiva($datos['destino_id']);
                if (! $destino || (string) $destino->getKey() === $sucursalId) {
                    throw new ReglaNegocioException('Seleccione una sucursal de destino activa y distinta del origen.');
                }
                $extras = ['origen_id' => $sucursalId, 'origen_nombre' => $sucursal->nombre,
                    'destino_id' => (string) $destino->getKey(), 'destino_nombre' => $destino->nombre];
            }
            $items = [];
            $total = 0;
            foreach ($datos['items'] as $linea) {
                $producto = $this->productos->buscarPorId($linea['producto_id']);
                if (! $producto->activo) {
                    throw new ReglaNegocioException('El producto «'.$producto->nombre.'» está inactivo.');
                }
                if ($tipo === 'ingresos' && $producto->proveedor_id !== $datos['proveedor_id']) {
                    throw new ReglaNegocioException('«'.$producto->nombre.'» pertenece a otro proveedor.');
                }
                $item = ['producto_id' => (string) $producto->getKey(), 'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre, 'cantidad' => $linea['cantidad']];
                if ($tipo !== 'traslados') {
                    $precio = $this->centavos($tipo === 'ventas' ? $producto->precio_venta : $producto->precio_costo);
                    $subtotal = $precio * $linea['cantidad'];
                    $item += ['precio_centavos' => $precio, 'subtotal_centavos' => $subtotal];
                    $total += $subtotal;
                }
                $items[] = $item;
            }
            if ($total > 9999999999) {
                throw new ReglaNegocioException('El total excede Q 99,999,999.99.');
            }
            if ($tipo === 'ventas') {
                if (isset($datos['total_esperado_centavos']) && (int) $datos['total_esperado_centavos'] !== $total) {
                    throw new ReglaNegocioException('Los precios cambiaron. Vuelva al detalle, actualice los productos y revise el cobro.', 409);
                }
                $efectivo = $this->centavos((string) $datos['efectivo']);
                if ($efectivo < $total) {
                    throw new ReglaNegocioException('El efectivo recibido es menor al total de la venta.');
                }
                $extras += ['efectivo_centavos' => $efectivo, 'cambio_centavos' => $efectivo - $total];
            }
            $documento = $this->repository->crear($tipo, array_merge($extras, [
                'operacion_id' => $datos['operacion_id'], 'huella' => $huella,
                'numero' => $this->repository->siguiente($tipo.':'.$sucursalId),
                'sucursal_id' => $sucursalId, 'sucursal_nombre' => $sucursal->nombre,
                'sucursal_direccion' => $sucursal->direccion, 'sucursal_telefono' => $sucursal->telefono,
                'usuario_id' => (string) $usuario->getKey(), 'usuario_nombre' => $usuario->nombre,
                'motivo' => $datos['motivo'] ?? '', 'items' => $items, 'total_centavos' => $total,
                'estado' => match ($tipo) {
                    'ventas' => 'emitida', 'ingresos' => 'registrado', 'traslados' => 'enviado'
                },
            ]));
            foreach ($items as $item) {
                $this->inventario->mover($item, $sucursalId, ($tipo === 'ingresos' ? 1 : -1) * $item['cantidad'],
                    $this->rastro($documento, $tipo, match ($tipo) {
                        'ingresos' => 'ingreso', 'ventas' => 'venta', 'traslados' => 'traslado_salida'
                    }, $usuario, $documento->motivo));
            }

            return $documento;
        });
    }

    public function finalizar(string $tipo, string $id, string $accion, string $motivo, Usuario $usuario): DocumentoInventario
    {
        return $this->atomica(function () use ($tipo, $id, $accion, $motivo, $usuario) {
            $documento = $this->buscar($tipo, $id, $usuario);
            if ($tipo !== 'traslados' && $usuario->rol !== 'superusuario') {
                throw new ReglaNegocioException('Solo el superusuario puede anular ingresos y ventas.', 403);
            }
            $confirmar = $tipo === 'traslados' && $accion === 'confirmar';
            if ($tipo === 'traslados'
                && $usuario->sucursal_id !== ($confirmar ? $documento->destino_id : $documento->origen_id)) {
                throw new ReglaNegocioException($confirmar ? 'Solo el destino puede confirmar la recepción.' : 'Solo el origen puede anular el envío.', 403);
            }
            $esperado = match ($tipo) {
                'ingresos' => 'registrado', 'ventas' => 'emitida', 'traslados' => 'enviado'
            };
            $prefijo = $confirmar ? 'confirmado' : 'anulado';
            if (! $this->repository->cambiarEstado($tipo, $id, $esperado, [
                'estado' => $confirmar ? 'confirmado' : 'anulado', $prefijo.'_por_id' => (string) $usuario->getKey(),
                $prefijo.'_por' => $usuario->nombre, $prefijo.'_at' => now()->toIso8601String(),
                $confirmar ? 'observacion' : 'motivo_anulacion' => $motivo,
            ])) {
                throw new ReglaNegocioException('El documento ya fue confirmado o anulado.', 409);
            }
            foreach ($documento->items as $item) {
                $movimiento = $confirmar ? 'traslado_entrada' : match ($tipo) {
                    'ingresos' => 'ingreso_anulado', 'ventas' => 'venta_anulada', 'traslados' => 'traslado_anulado'
                };
                $this->inventario->mover($item, $confirmar ? $documento->destino_id : $documento->sucursal_id,
                    ($tipo === 'ingresos' ? -1 : 1) * $item['cantidad'], $this->rastro($documento, $tipo, $movimiento, $usuario, $motivo));
            }

            return $this->repository->buscar($tipo, $id);
        });
    }

    private function atomica(Closure $accion): mixed
    {
        if (! $this->repository->preparado()) {
            throw new ReglaNegocioException('El inventario no está disponible. Contacte al administrador.', 503);
        }
        // Una colisión al crear existencias, correlativos o claves exige reintentar todo.
        for ($intento = 0; $intento < 3; $intento++) {
            try {
                return $this->repository->atomica($accion);
            } catch (BulkWriteException $error) {
                if ($error->getCode() !== 11000) {
                    throw $error;
                }
            }
        }
        throw new ReglaNegocioException('Otra operación actualizó el inventario. Reintente con el mismo formulario.', 409);
    }

    private function sucursalConsulta(array $filtros, Usuario $usuario): ?string
    {
        $id = $filtros['sucursal_id'] ?? null;
        if ($usuario->rol !== 'superusuario') {
            if ($id !== null && $id !== $usuario->sucursal_id) {
                throw new ReglaNegocioException('No tiene permiso para consultar otra sucursal.', 403);
            }

            return $usuario->sucursal_id;
        }
        if ($id !== null) {
            $this->sucursales->buscarPorId($id);
        }

        return $id;
    }

    private function centavos(string $importe): int
    {
        [$entero, $decimal] = array_pad(explode('.', $importe), 2, '');

        return ((int) $entero * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function rastro(DocumentoInventario $doc, string $tipo, string $movimiento, Usuario $usuario, string $motivo): array
    {
        return ['documento_id' => (string) $doc->getKey(), 'tipo_documento' => $tipo, 'numero' => $doc->numero,
            'tipo' => $movimiento, 'usuario_id' => (string) $usuario->getKey(), 'usuario_nombre' => $usuario->nombre, 'motivo' => $motivo];
    }

    private function ordenar(array $datos): array
    {
        if (! array_is_list($datos)) {
            ksort($datos);
        }
        foreach ($datos as &$valor) {
            if (is_array($valor)) {
                $valor = $this->ordenar($valor);
            }
        }

        return $datos;
    }
}
