<?php

namespace App\Repositories;

use App\Models\Movimiento;

class MovimientoRepository
{
    public function crear(array $datos): Movimiento
    {
        return Movimiento::create($datos);
    }

    public function filtrar(array $filtros, ?string $sucursal): array
    {
        $consulta = Movimiento::query();
        if ($sucursal !== null) {
            $consulta->where('sucursal_id', $sucursal);
        }
        if (! empty($filtros['producto_id'])) {
            $consulta->where('producto_id', $filtros['producto_id']);
        }

        return $consulta->orderByDesc('created_at')->orderByDesc('_id')->limit(200)->get()->toArray();
    }
}
