<?php

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\ClienteRepository;
use App\Repositories\SucursalRepository;
use Illuminate\Support\Arr;

class CatalogoOperacionService
{
    public function __construct(private ProductoService $productos, private PermisoService $permisos,
        private SucursalRepository $sucursales, private ClienteRepository $clientes) {}

    public function productos(array $filtros, Usuario $usuario): array
    {
        $campos = ['_id', 'codigo', 'nombre', 'precio_venta', 'proveedor_id', 'existencia'];
        if ($this->permisos->puede($usuario, 'ingresos')) {
            $campos[] = 'precio_costo';
        }

        return array_map(fn ($producto) => Arr::only($producto, $campos),
            $this->productos->filtrar(array_merge($filtros, ['activo' => true]), $usuario));
    }

    public function destinos(): array
    {
        return $this->sucursales->obtenerTodos()->filter(fn ($sucursal) => $sucursal->activo)
            ->map(fn ($sucursal) => ['_id' => (string) $sucursal->getKey(), 'nombre' => $sucursal->nombre])->values()->all();
    }

    public function clientes(array $filtros): array
    {
        return $this->clientes->filtrar(Arr::only($filtros, ['q', 'nit']))->map(fn ($cliente) => [
            '_id' => (string) $cliente->getKey(), 'nombre' => $cliente->nombre, 'nit' => $cliente->nit,
        ])->all();
    }
}
