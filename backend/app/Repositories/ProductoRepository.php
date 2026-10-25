<?php

namespace App\Repositories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;

class ProductoRepository
{
    public function filtrar(array $filtros): Collection
    {
        $consulta = Producto::query();
        if (isset($filtros['codigo']) && $filtros['codigo'] !== '') {
            $consulta->where('codigo', 'regex', new Regex('^'.preg_quote(trim($filtros['codigo']), '/').'$', 'i'));
        }
        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $regex = new Regex(preg_quote($texto), 'i');
            $consulta->where(function ($query) use ($regex) {
                foreach (['codigo', 'nombre', 'descripcion', 'categoria'] as $campo) {
                    $query->orWhere($campo, 'regex', $regex);
                }
            });
        }
        foreach (['proveedor_id', 'categoria'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['activo'])) {
            $consulta->where('activo', (bool) $filtros['activo']);
        }

        return $consulta->orderBy($filtros['orden'] ?? 'nombre', $filtros['dir'] ?? 'asc')->orderBy('_id')->limit(500)->get();
    }

    public function buscarPorId(string $id): Producto
    {
        abort_unless(preg_match('/^[a-f0-9]{24}$/i', $id), 404);

        return Producto::findOrFail($id);
    }

    public function existeCodigo(string $codigo, ?string $exceptoId = null): bool
    {
        $consulta = Producto::where('codigo', 'regex', new Regex('^'.preg_quote($codigo).'$', 'i'));
        if ($exceptoId !== null) {
            $consulta->where('_id', '!=', $exceptoId);
        }

        return $consulta->exists();
    }

    public function crear(array $datos): Producto
    {
        return Producto::create($datos);
    }

    public function actualizar(string $id, array $datos): Producto
    {
        $producto = $this->buscarPorId($id);
        $producto->update($datos);

        return $producto->fresh();
    }

    public function eliminar(string $id): void
    {
        $this->buscarPorId($id)->delete();
    }

    public function enUso(string $id): bool
    {
        foreach ([['movimientos', 'producto_id'], ['existencias', 'producto_id'], ['ventas', 'items.producto_id'], ['traslados', 'items.producto_id'], ['ingresos', 'items.producto_id']] as [$coleccion, $campo]) {
            if (DB::connection('mongodb')->table($coleccion)->where($campo, $id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
