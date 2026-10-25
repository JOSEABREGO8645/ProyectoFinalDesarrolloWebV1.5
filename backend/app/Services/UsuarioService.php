<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Repositories\SesionRepository;
use App\Repositories\SucursalRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Hash;
use MongoDB\Driver\Exception\BulkWriteException;

class UsuarioService
{
    public function __construct(
        private UsuarioRepository $repository,
        private SesionRepository $sesiones,
        private SucursalRepository $sucursales,
    ) {}

    public function filtrar(array $filtros): array
    {
        $sucursales = $this->sucursales->obtenerTodos()->mapWithKeys(fn ($sucursal) => [(string) $sucursal->getKey() => $sucursal->nombre]);

        return $this->repository->filtrar($filtros)->map(fn (Usuario $usuario) => array_merge($usuario->toArray(), [
            'sucursal_nombre' => $sucursales[$usuario->sucursal_id] ?? null,
            'sucursal_ids' => $usuario->sucursalesAsignadas(),
            'sucursal_nombres' => array_values(array_filter(array_map(fn ($id) => $sucursales[$id] ?? null, $usuario->sucursalesAsignadas()))),
            'tiene_codigo' => (bool) $usuario->codigo_hash,
        ]))->all();
    }

    public function buscar(string $id): array
    {
        $usuario = $this->repository->buscarPorId($id);

        return array_merge($usuario->toArray(), [
            'sucursal_nombre' => $this->sucursales->buscarPorId($usuario->sucursal_id)->nombre,
            'sucursal_ids' => $usuario->sucursalesAsignadas(),
            'tiene_codigo' => (bool) $usuario->codigo_hash,
        ]);
    }

    public function agregar(array $datos): Usuario
    {
        $datos = $this->normalizar($datos);
        $this->validar($datos);

        return $this->guardar(fn () => $this->repository->crear($datos));
    }

    public function editar(string $id, array $datos, Usuario $actor): Usuario
    {
        $actual = $this->repository->buscarPorId($id);
        $datos = $this->normalizar($datos, $actual);
        if ((string) $actor->getKey() === $id && ($datos['rol'] !== 'superusuario' || ! $datos['activo'])) {
            throw new ReglaNegocioException('No puede quitarse su propio rol de superusuario ni desactivarse.');
        }
        $this->validar($datos, $id);
        $revocar = isset($datos['password']);
        $revocar = $revocar || $actual->sucursalesAsignadas() !== $datos['sucursal_ids'];
        foreach (['rol', 'activo', 'sucursal_id', 'modulos'] as $campo) {
            $revocar = $revocar || $actual->{$campo} !== $datos[$campo];
        }
        $usuario = $this->guardar(fn () => $this->repository->actualizar($id, $datos, $revocar));
        if ($revocar) {
            $this->sesiones->revocarPorUsuario($id, now()->timestamp);
        }

        return $usuario;
    }

    public function eliminar(string $id, Usuario $actor): void
    {
        $this->repository->buscarPorId($id);
        if ((string) $actor->getKey() === $id) {
            throw new ReglaNegocioException('No puede eliminar su propio usuario.', 409);
        }
        if ($this->repository->enUso($id)) {
            throw new ReglaNegocioException('Este usuario tiene operaciones registradas. Desactívelo en lugar de eliminarlo.', 409);
        }
        $this->sesiones->revocarPorUsuario($id, now()->timestamp);
        $this->repository->eliminar($id);
    }

    public function crearInicial(array $datos): Usuario
    {
        if ($this->repository->existeSuperusuario()) {
            throw new ReglaNegocioException('Ya existe un superusuario activo. Administre las cuentas desde Usuarios.', 409);
        }

        return $this->agregar(array_merge($datos, ['rol' => 'superusuario', 'modulos' => [], 'activo' => true]));
    }

    private function validar(array $datos, ?string $id = null): void
    {
        if ($this->repository->existeUsuario($datos['usuario'], $id)) {
            throw new ReglaNegocioException('Ya existe una cuenta con ese usuario.');
        }
        if (! $datos['sucursal_ids'] || ! in_array($datos['sucursal_id'], $datos['sucursal_ids'], true)) {
            throw new ReglaNegocioException('La sucursal principal debe estar entre las sucursales asignadas.');
        }
        foreach ($datos['sucursal_ids'] as $idSucursal) {
            $sucursal = $this->sucursales->encontrar($idSucursal);
            if ($sucursal === null) {
                throw new ReglaNegocioException('La sucursal seleccionada no existe.');
            }
            if ($datos['activo'] && ! $sucursal->activo) {
                throw new ReglaNegocioException('Seleccione una sucursal activa para este usuario.');
            }
        }
    }

    private function normalizar(array $datos, ?Usuario $actual = null): array
    {
        $datos['nombre'] = trim($datos['nombre']);
        $datos['usuario'] = mb_strtolower(trim($datos['usuario']));
        $datos['sucursal_id'] = mb_strtolower($datos['sucursal_id']);
        $datos['sucursal_ids'] = array_values(array_unique(array_map('mb_strtolower', $datos['sucursal_ids'] ?? array_unique([...($actual?->sucursalesAsignadas() ?? []), $datos['sucursal_id']]))));
        $datos['activo'] = (bool) ($datos['activo'] ?? $actual?->activo ?? true);
        $datos['modulos'] = $datos['rol'] === 'superusuario' ? [] : array_values(array_unique($datos['modulos'] ?? (array) $actual?->modulos));
        if (! empty($datos['password'])) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }
        if (! empty($datos['codigo'])) {
            $datos['codigo_hash'] = Hash::make($datos['codigo']);
        }
        unset($datos['codigo']);

        return $datos;
    }

    private function guardar(callable $operacion): Usuario
    {
        try {
            return $operacion();
        } catch (BulkWriteException $e) {
            if ($e->getCode() === 11000) {
                throw new ReglaNegocioException('Ya existe una cuenta con ese usuario.');
            }
            throw $e;
        }
    }
}
