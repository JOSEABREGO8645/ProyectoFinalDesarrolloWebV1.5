<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Producto;
use App\Models\Usuario;
use App\Repositories\ExistenciaRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\SucursalRepository;
use Illuminate\Database\Eloquent\Collection;

class ProductoService
{
    public function __construct(
        private ProductoRepository $repository,
        private ProveedorRepository $proveedores,
        private ExistenciaRepository $existencias,
        private SucursalRepository $sucursales,
    ) {}

    public function filtrar(array $filtros, Usuario $usuario): array
    {
        $sucursalId = $filtros['sucursal_id'] ?? null;
        if ($usuario->rol !== 'superusuario') {
            if ($sucursalId !== null && $sucursalId !== $usuario->sucursal_id) {
                throw new ReglaNegocioException('No tiene permiso para consultar otra sucursal.', 403);
            }
            $sucursalId = $usuario->sucursal_id;
        }
        if ($sucursalId !== null) {
            $this->sucursales->buscarPorId($sucursalId);
        }

        return $this->enriquecer($this->repository->filtrar($filtros), $sucursalId);
    }

    public function buscar(string $id, Usuario $usuario): array
    {
        $producto = $this->repository->buscarPorId($id);

        return $this->enriquecer($producto->newCollection([$producto]), $usuario->rol === 'superusuario' ? null : $usuario->sucursal_id)[0];
    }

    public function agregar(array $datos): Producto
    {
        $datos = $this->normalizar($datos);
        $this->validar($datos);

        return $this->repository->crear($datos);
    }

    public function editar(string $id, array $datos): Producto
    {
        $this->repository->buscarPorId($id);
        $datos = $this->normalizar($datos);
        $this->validar($datos, $id);

        return $this->repository->actualizar($id, $datos);
    }

    public function eliminar(string $id): void
    {
        $this->repository->buscarPorId($id);
        if ($this->repository->enUso($id)) {
            throw new ReglaNegocioException('Este producto tiene existencias o historial. Desactívelo en lugar de eliminarlo.', 409);
        }
        $this->repository->eliminar($id);
    }

    private function validar(array $datos, ?string $id = null): void
    {
        if ($this->repository->existeCodigo($datos['codigo'], $id)) {
            throw new ReglaNegocioException('Ya existe un producto con ese código.');
        }
        if ($datos['proveedor_id'] !== null && ! $this->proveedores->existe($datos['proveedor_id'])) {
            throw new ReglaNegocioException('El proveedor seleccionado no existe.');
        }
    }

    private function normalizar(array $datos): array
    {
        foreach ($datos as $campo => $valor) {
            if (is_string($valor)) {
                $datos[$campo] = trim($valor);
            }
        }
        $datos['proveedor_id'] = empty($datos['proveedor_id']) ? null : mb_strtolower($datos['proveedor_id']);
        $datos['stock_minimo'] = (int) ($datos['stock_minimo'] ?? 0);
        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        return $datos;
    }

    private function enriquecer(Collection $productos, ?string $sucursalId = null): array
    {
        $ids = $productos->map(fn (Producto $producto) => (string) $producto->getKey())->all();
        $proveedores = $this->proveedores->nombresPorIds($productos->pluck('proveedor_id')->filter()->unique()->values()->all());
        $existencias = $this->existencias->porProductos($ids, $sucursalId)->groupBy('producto_id');

        return $productos->map(fn (Producto $producto) => array_merge($producto->toArray(), [
            'proveedor_nombre' => $proveedores[$producto->proveedor_id] ?? null,
            'existencia' => (int) ($existencias->get((string) $producto->getKey())?->sum('cantidad') ?? 0),
        ]))->all();
    }
}
