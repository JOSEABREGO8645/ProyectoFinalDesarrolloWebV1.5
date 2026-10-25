<?php

namespace App\Repositories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;

class ClienteRepository
{
    public function obtenerTodos(): Collection
    {
        return $this->filtrar([]);
    }

    public function filtrar(array $filtros): Collection
    {
        $consulta = Cliente::query();
        if (isset($filtros['nit']) && trim($filtros['nit']) !== '') {
            $consulta->where('nit', mb_strtoupper(preg_replace('/[-\\s]+/u', '', $filtros['nit'])));
        }
        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $regex = new Regex(preg_quote($texto), 'i');
            $consulta->where(function ($query) use ($regex) {
                foreach (['nombre', 'nit', 'telefono', 'correo', 'direccion'] as $campo) {
                    $query->orWhere($campo, 'regex', $regex);
                }
            });
        }

        return $consulta->orderBy($filtros['orden'] ?? 'nombre', $filtros['dir'] ?? 'asc')
            ->orderBy('_id')->limit(500)->get();
    }

    public function buscarPorId(string $id): Cliente
    {
        abort_unless(preg_match('/^[a-f0-9]{24}$/i', $id), 404);

        return Cliente::findOrFail($id);
    }

    public function crear(array $datos): Cliente
    {
        return Cliente::create($datos);
    }

    public function actualizar(string $id, array $datos): Cliente
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
        return DB::connection('mongodb')->table('ventas')->where('cliente_id', $id)->exists();
    }

    public function buscarPorNit(string $nit): ?Cliente
    {
        return Cliente::where('nit', $nit)->first();
    }

    public function existeNit(string $nit, ?string $exceptoId = null): bool
    {
        $consulta = Cliente::where('nit', $nit);
        if ($exceptoId !== null) {
            $consulta->where('_id', '!=', $exceptoId);
        }

        return $consulta->exists();
    }
}
