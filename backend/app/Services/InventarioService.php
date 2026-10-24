<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Repositories\ExistenciaRepository;
use App\Repositories\MovimientoRepository;
use App\Repositories\OperacionRepository;
use LogicException;

class InventarioService
{
    public function __construct(private ExistenciaRepository $existencias, private MovimientoRepository $movimientos, private OperacionRepository $operaciones) {}

    /** Se ejecuta únicamente dentro de la transacción del documento. */
    public function mover(array $item, string $sucursal, int $cantidad, array $rastro): void
    {
        if (! $this->operaciones->enTransaccion()) {
            throw new LogicException('Los movimientos requieren la transacción de un documento.');
        }
        if ($cantidad === 0) {
            throw new ReglaNegocioException('La cantidad debe ser distinta de cero.');
        }
        $existencia = $this->existencias->obtener($item['producto_id'], $sucursal);
        $antes = $existencia->cantidad;
        $despues = $antes + $cantidad;
        if ($despues < 0) {
            throw new ReglaNegocioException('Existencias insuficientes de «'.$item['nombre'].'».', 409);
        }
        if ($despues > 100000000) {
            throw new ReglaNegocioException('La existencia supera el máximo permitido.');
        }
        if (! $this->existencias->cambiar($existencia, $antes, $despues)) {
            throw new ReglaNegocioException('El inventario cambió. Intente nuevamente.', 409);
        }
        $this->movimientos->crear(array_merge($rastro, [
            'producto_id' => $item['producto_id'], 'codigo' => $item['codigo'], 'nombre' => $item['nombre'],
            'sucursal_id' => $sucursal, 'cantidad' => $cantidad, 'antes' => $antes, 'despues' => $despues,
        ]));
    }
}
