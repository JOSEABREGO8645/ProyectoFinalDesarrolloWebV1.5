<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Repositories\SucursalRepository;

class SucursalService
{
    public function __construct(
        private SucursalRepository $repository
    ) {}

    public function listar()
    {
        return $this->repository->obtenerTodos();
    }

    public function filtrar(array $filtros)
    {
        return $this->repository->filtrar($filtros);
    }

    public function buscar(string $id)
    {
        return $this->repository->buscarPorId($id);
    }

    public function agregar(array $datos)
    {
        $datos = $this->normalizar($datos);
        $this->validarNombreUnico($datos['nombre']);

        return $this->repository->crear($datos);
    }

    public function editar(string $id, array $datos)
    {
        $this->repository->buscarPorId($id); // 404 si no existe
        $datos = $this->normalizar($datos);
        $this->validarNombreUnico($datos['nombre'], $id);
        if (! $datos['activo'] && $this->repository->tieneUsuariosActivos($id)) {
            throw new ReglaNegocioException('Reasigne o desactive los usuarios de esta sucursal antes de desactivarla.', 409);
        }

        return $this->repository->actualizar($id, $datos);
    }

    public function eliminar(string $id): void
    {
        $this->repository->buscarPorId($id);

        if ($this->repository->enUso($id)) {
            throw new ReglaNegocioException(
                'Esta sucursal tiene empleados, existencias, ventas o traslados. Desactívela en lugar de eliminarla.',
                409
            );
        }

        $this->repository->eliminar($id);
    }

    private function normalizar(array $datos): array
    {
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        return $datos;
    }

    private function validarNombreUnico(string $nombre, ?string $exceptoId = null): void
    {
        if ($this->repository->existeNombre($nombre, $exceptoId)) {
            throw new ReglaNegocioException('Ya existe una sucursal con ese nombre.');
        }
    }
}
