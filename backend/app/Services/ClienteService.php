<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    public function __construct(private ClienteRepository $repository) {}

    public function listar(): Collection
    {
        return $this->repository->obtenerTodos();
    }

    public function filtrar(array $filtros): Collection
    {
        return $this->repository->filtrar($filtros);
    }

    public function buscar(string $id): Cliente
    {
        return $this->repository->buscarPorId($id);
    }

    public function agregar(array $datos): Cliente
    {
        $datos = $this->normalizar($datos);
        $this->validarNitUnico($datos['nit']);

        return $this->repository->crear($datos);
    }

    public function editar(string $id, array $datos): Cliente
    {
        $this->repository->buscarPorId($id);
        $datos = $this->normalizar($datos);
        $this->validarNitUnico($datos['nit'], $id);

        return $this->repository->actualizar($id, $datos);
    }

    public function eliminar(string $id): void
    {
        $this->repository->buscarPorId($id);
        if ($this->repository->enUso($id)) {
            throw new ReglaNegocioException('Este cliente tiene ventas registradas y no puede eliminarse.', 409);
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
        $datos['nit'] = $this->limpiarNit($datos['nit']);
        if ($datos['nit'] === '') {
            throw new ReglaNegocioException('El NIT es obligatorio; use CF para consumidor final.');
        }

        return $datos;
    }

    public function porNit(string $nit): Cliente
    {
        $cliente = $this->repository->buscarPorNit($this->limpiarNit($nit));
        abort_if($cliente === null, 404);

        return $cliente;
    }

    private function limpiarNit(string $nit): string
    {
        return mb_strtoupper(preg_replace('/[-\s]+/u', '', $nit));
    }

    private function validarNitUnico(string $nit, ?string $exceptoId = null): void
    {
        if ($this->repository->existeNit($nit, $exceptoId)) {
            throw new ReglaNegocioException('Ya existe un cliente con ese NIT.');
        }
    }
}
