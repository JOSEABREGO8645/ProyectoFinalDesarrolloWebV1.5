<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Illuminate\Database\Eloquent\Collection;

class ProveedorService
{
    public function opciones(): array
    {
        return $this->repository->obtenerTodos()->map(fn (Proveedor $proveedor) => [
            '_id' => (string) $proveedor->getKey(), 'nombre' => $proveedor->nombre, 'activo' => (bool) $proveedor->activo,
        ])->all();
    }

    public function __construct(private ProveedorRepository $repository) {}

    public function listar(): Collection
    {
        return $this->repository->obtenerTodos();
    }

    public function filtrar(array $filtros): Collection
    {
        return $this->repository->filtrar($filtros);
    }

    public function buscar(string $id): Proveedor
    {
        return $this->repository->buscarPorId($id);
    }

    public function agregar(array $datos): Proveedor
    {
        $datos = $this->normalizar($datos);

        return $this->repository->crear($datos);
    }

    public function editar(string $id, array $datos): Proveedor
    {
        $this->repository->buscarPorId($id);
        $datos = $this->normalizar($datos);

        return $this->repository->actualizar($id, $datos);
    }

    public function eliminar(string $id): void
    {
        $this->repository->buscarPorId($id);
        if ($this->repository->enUso($id)) {
            throw new ReglaNegocioException('Este proveedor tiene productos o ingresos registrados. Desact?velo en lugar de eliminarlo.', 409);
        }
        $this->repository->eliminar($id);
    }

    private function normalizar(array $datos): array
    {
        foreach ($datos as $campo => $valor) {
            if (is_string($valor)) {
                $datos[$campo] = trim($valor);
            }
        }
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        return $datos;
    }
}
