<?php

namespace App\Repositories;

use App\Models\Existencia;
use Illuminate\Database\Eloquent\Collection;

class ExistenciaRepository
{
    public function obtener(string $producto, string $sucursal): Existencia
    {
        return Existencia::firstOrCreate(['producto_id' => $producto, 'sucursal_id' => $sucursal], ['cantidad' => 0]);
    }

    public function cambiar(Existencia $existencia, int $cantidadEsperada, int $cantidadNueva): bool
    {
        return Existencia::where('_id', (string) $existencia->getKey())->where('cantidad', $cantidadEsperada)
            ->update(['cantidad' => $cantidadNueva]) === 1;
    }

    public function porProductos(array $ids, ?string $sucursalId = null): Collection
    {
        $consulta = Existencia::whereIn('producto_id', $ids);
        if ($sucursalId !== null) {
            $consulta->where('sucursal_id', $sucursalId);
        }

        return $consulta->get();
    }
}
