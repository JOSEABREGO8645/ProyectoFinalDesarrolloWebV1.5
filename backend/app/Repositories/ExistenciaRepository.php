<?php

namespace App\Repositories;

use App\Models\Existencia;
use Illuminate\Database\Eloquent\Collection;

class ExistenciaRepository
{
    public function porProductos(array $ids, ?string $sucursalId = null): Collection
    {
        $consulta = Existencia::whereIn('producto_id', $ids);
        if ($sucursalId !== null) {
            $consulta->where('sucursal_id', $sucursalId);
        }

        return $consulta->get();
    }
}
