<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;

class ProveedorRepository
{
    public function obtenerTodos(): Collection
    {
        return $this->filtrar([]);
    }

    public function filtrar(array $filtros): Collection
    {
        $consulta = Proveedor::query();
        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $regex = new Regex(preg_quote($texto), 'i');
            $consulta->where(function ($query) use ($regex) {
                foreach (['nombre', 'nit', 'telefono', 'correo', 'direccion', 'contacto'] as $campo) {
                    $query->orWhere($campo, 'regex', $regex);
                }
            });
        }
        if (isset($filtros['activo'])) {
            $consulta->where('activo', (bool) $filtros['activo']);
        }

        return $consulta->orderBy($filtros['orden'] ?? 'nombre', $filtros['dir'] ?? 'asc')
            ->orderBy('_id')->limit(500)->get();
    }

    public function buscarPorId(string $id): Proveedor
    {
        abort_unless(preg_match('/^[a-f0-9]{24}$/i', $id), 404);

        return Proveedor::findOrFail($id);
    }

    public function crear(array $datos): Proveedor
    {
        return Proveedor::create($datos);
    }

    public function actualizar(string $id, array $datos): Proveedor
    {
        $registro = $this->buscarPorId($id);
        $registro->update($datos);

        return $registro->fresh();
    }

    public function eliminar(string $id): void
    {
        $this->buscarPorId($id)->delete();
    }

    public function enUso(string $id): bool
    {
        return DB::connection('mongodb')->table('productos')->where('proveedor_id', $id)->exists()
            || DB::connection('mongodb')->table('ingresos')->where('proveedor_id', $id)->exists();
    }

    public function nombresPorIds(array $ids): array
    {
        return Proveedor::whereIn('_id', $ids)->get()->mapWithKeys(fn (Proveedor $proveedor) => [(string) $proveedor->getKey() => $proveedor->nombre])->all();
    }

    public function existe(string $id): bool
    {
        return Proveedor::where('_id', $id)->exists();
    }
}
