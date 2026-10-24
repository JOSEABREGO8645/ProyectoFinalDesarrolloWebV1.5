<?php

namespace App\Repositories;

use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

class SucursalRepository
{
    private const ORDENABLES = ['nombre', 'direccion', 'telefono', 'created_at'];

    public function obtenerTodos()
    {
        return Sucursal::orderBy('nombre')->get();
    }

    /** Texto + estado + ordenamiento (ruta GET /sucursales/buscar). */
    public function filtrar(array $filtros)
    {
        $consulta = Sucursal::query();

        if (! empty($filtros['q'])) {
            $texto = '%'.$filtros['q'].'%';
            $consulta->where(function ($w) use ($texto) {
                foreach (['nombre', 'direccion', 'telefono'] as $campo) {
                    $w->orWhere($campo, 'like', $texto);
                }
            });
        }

        if (isset($filtros['activo'])) {
            $consulta->where('activo', (bool) $filtros['activo']);
        }

        $orden = in_array($filtros['orden'] ?? null, self::ORDENABLES, true) ? $filtros['orden'] : 'nombre';
        $direccion = ($filtros['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $consulta->orderBy($orden, $direccion)->limit(500)->get();
    }

    public function buscarPorId(string $id)
    {
        return Sucursal::findOrFail($id);
    }

    public function encontrarActiva(string $id): ?Sucursal
    {
        return preg_match('/^[a-f0-9]{24}$/i', $id) ? Sucursal::where('_id', $id)->where('activo', true)->first() : null;
    }

    public function encontrar(string $id): ?Sucursal
    {
        return preg_match('/^[a-f0-9]{24}$/i', $id) ? Sucursal::find($id) : null;
    }

    public function crear(array $datos)
    {
        return Sucursal::create($datos);
    }

    public function actualizar(string $id, array $datos)
    {
        $sucursal = $this->buscarPorId($id);
        $sucursal->update($datos);

        return $sucursal->fresh();
    }

    public function eliminar(string $id): void
    {
        $this->buscarPorId($id)->delete();
    }

    public function existeNombre(string $nombre, ?string $exceptoId = null): bool
    {
        $consulta = Sucursal::where('nombre', 'like', $nombre);

        if ($exceptoId) {
            $consulta->where('_id', '!=', $exceptoId);
        }

        return $consulta->exists();
    }

    /** ¿Otras colecciones dependen de esta sucursal? Si sí, no se elimina (trazabilidad). */
    public function enUso(string $id): bool
    {
        $referencias = [
            ['usuarios', 'sucursal_id'],
            ['existencias', 'sucursal_id'],
            ['ventas', 'sucursal_id'],
            ['ingresos', 'sucursal_id'],
            ['movimientos', 'sucursal_id'],
            ['traslados', 'origen_id'],
            ['traslados', 'destino_id'],
        ];

        foreach ($referencias as [$coleccion, $campo]) {
            if (DB::connection('mongodb')->table($coleccion)->where($campo, $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function tieneUsuariosActivos(string $id): bool
    {
        return DB::connection('mongodb')->table('usuarios')->where('sucursal_id', $id)->where('activo', true)->exists();
    }
}
