<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Repositories\ClienteRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\ReporteRepository;
use App\Repositories\SucursalRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use MongoDB\BSON\UTCDateTime;

class ReporteService
{
    public function __construct(private ReporteRepository $repository, private SucursalRepository $sucursales,
        private ProductoRepository $productos, private ProveedorRepository $proveedores, private ClienteRepository $clientes) {}

    public function catalogo(): array
    {
        $hasta = CarbonImmutable::now(config('dashboard.timezone'))->startOfDay();
        $reportes = [];
        foreach (config('reportes') as $id => $definicion) {
            $reportes[] = array_merge(['id' => $id], $definicion);
        }

        return ['reportes' => $reportes, 'desde' => $hasta->subDays(29)->toDateString(), 'hasta' => $hasta->toDateString(), 'zona' => config('dashboard.timezone')];
    }

    public function productos(array $filtros): array
    {
        return $this->productos->filtrar(['q' => $filtros['q'] ?? ''])->map(fn ($p) => [
            '_id' => (string) $p->getKey(), 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'activo' => (bool) $p->activo,
        ])->all();
    }

    public function preparar(string $tipo, array $datos, Usuario $usuario): array
    {
        $definicion = (config('reportes')[$tipo] ?? null);
        abort_if(! is_array($definicion), 404);
        foreach (['q', 'categoria', 'producto_id', 'cliente_id', 'proveedor_id', 'estado'] as $campo) {
            if (isset($datos[$campo]) && $datos[$campo] !== '' && ! in_array($campo, $definicion['filtros'], true)) {
                throw new ReglaNegocioException('El filtro seleccionado no corresponde a este reporte.');
            }
        }
        if (! $definicion['fechas'] && (! empty($datos['desde']) || ! empty($datos['hasta']))) {
            throw new ReglaNegocioException('Este reporte muestra el inventario actual y no utiliza fechas.');
        }
        $sucursal = $datos['sucursal_id'] ?? null;
        if ($usuario->rol !== 'superusuario') {
            if ($sucursal !== null && $sucursal !== $usuario->sucursal_id) {
                throw new ReglaNegocioException('No tiene permiso para consultar otra sucursal.', 403);
            }
            $sucursal = $usuario->sucursal_id;
        }
        $nombre = $sucursal === null ? 'Todas las sucursales' : $this->sucursales->buscarPorId($sucursal)->nombre;
        $filtros = array_merge($datos, ['sucursal_id' => $sucursal, 'zona' => config('dashboard.timezone')]);
        $publicos = ['sucursal_id' => $sucursal, 'sucursal_nombre' => $nombre, 'desde' => null, 'hasta' => null, 'zona' => $filtros['zona']];
        if ($definicion['fechas']) {
            $hasta = isset($datos['hasta']) ? CarbonImmutable::createFromFormat('!Y-m-d', $datos['hasta'], $filtros['zona']) : CarbonImmutable::now($filtros['zona'])->startOfDay();
            $desde = isset($datos['desde']) ? CarbonImmutable::createFromFormat('!Y-m-d', $datos['desde'], $filtros['zona']) : $hasta->subDays(29);
            if ($desde->diffInDays($hasta) > 365) {
                throw new ReglaNegocioException('Seleccione un período de hasta 366 días.');
            }
            $filtros['inicio_utc'] = new UTCDateTime($desde->utc());
            $filtros['fin_utc'] = new UTCDateTime($hasta->addDay()->utc());
            $publicos['desde'] = $desde->toDateString();
            $publicos['hasta'] = $hasta->toDateString();
        }
        foreach (['producto_id' => $this->productos, 'proveedor_id' => $this->proveedores, 'cliente_id' => $this->clientes] as $campo => $repo) {
            if (! empty($datos[$campo])) {
                $registro = $repo->buscarPorId($datos[$campo]);
                $publicos[$campo] = $datos[$campo];
                $publicos[$campo.'_nombre'] = $registro->nombre;
            }
        }
        foreach (['q', 'categoria', 'estado'] as $campo) {
            if (isset($datos[$campo]) && trim($datos[$campo]) !== '') {
                $filtros[$campo] = trim($datos[$campo]);
                $publicos[$campo] = $filtros[$campo];
            }
        }

        return ['tipo' => $tipo, 'definicion' => $definicion, 'filtros' => $filtros, 'publicos' => $publicos];
    }

    public function consultar(array $contexto): array
    {
        $pagina = (int) ($contexto['filtros']['pagina'] ?? 1);
        $porPagina = (int) ($contexto['filtros']['por_pagina'] ?? 50);
        $especificacion = $this->totales($contexto['tipo']);
        $sumas = [];
        foreach ($especificacion as $key => $def) {
            $sumas[$key] = BigDecimal::zero();
        }
        $filas = [];
        $total = 0;
        foreach ($this->filas($contexto) as $fila) {
            if ($total >= ($pagina - 1) * $porPagina && count($filas) < $porPagina) {
                $filas[] = $fila;
            }
            $total++;
            $valores = $fila;
            if ($contexto['tipo'] === 'kardex') {
                $valores += ['entradas' => max(0, $fila['cantidad']), 'salidas' => max(0, -$fila['cantidad']), 'variacion' => $fila['cantidad']];
            }
            if ($contexto['tipo'] === 'flujo-traslados') {
                $valores += ['confirmadas' => $fila['estado'] === 'confirmado' ? $fila['cantidad'] : 0, 'pendientes' => $fila['estado'] === 'enviado' ? $fila['cantidad'] : 0];
            }
            if ($contexto['tipo'] === 'anulaciones') {
                $valores = ['ventas' => $fila['documento'] === 'ventas' ? 1 : 0, 'ingresos' => $fila['documento'] === 'ingresos' ? 1 : 0, 'traslados' => $fila['documento'] === 'traslados' ? 1 : 0];
            }
            foreach ($sumas as $key => $suma) {
                $sumas[$key] = $suma->plus((string) ($valores[$key] ?? 0));
            }
        }
        $resumen = [];
        foreach ($especificacion as $key => [$label,$tipo]) {
            $resumen[] = ['label' => $label, 'tipo' => $tipo, 'valor' => $tipo === 'dinero' ? (string) $sumas[$key]->toScale(2) : $sumas[$key]->toInt()];
        }

        return ['reporte' => ['id' => $contexto['tipo'], 'titulo' => $contexto['definicion']['titulo'], 'descripcion' => $contexto['definicion']['descripcion']],
            'columnas' => $contexto['definicion']['columnas'], 'filtros' => $contexto['publicos'], 'generado_at' => now()->toIso8601String(),
            'filas' => $filas, 'resumen' => $resumen, 'paginacion' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $total, 'paginas' => max(1, (int) ceil($total / $porPagina))]];
    }

    public function filas(array $c): iterable
    {
        $tipo = $c['tipo'];
        $f = $c['filtros'];
        if (in_array($tipo, ['existencias', 'valoracion'], true)) {
            $categorias = [];
            foreach ($this->repository->inventario($f) as $producto) {
                $categoria = trim((string) ($producto['categoria'] ?? ''));
                if ($categoria === '') {
                    $categoria = 'Sin categoría';
                }
                $cantidad = (int) $producto['cantidad'];
                if ($tipo === 'existencias') {
                    $activo = (bool) ($producto['activo'] ?? false);
                    $minimo = (int) ($producto['stock_minimo'] ?? 0);
                    yield ['codigo' => $producto['codigo'], 'nombre' => $producto['nombre'], 'categoria' => $categoria, 'cantidad' => $cantidad, 'minimo' => $minimo,
                        'estado' => ! $activo ? 'Inactivo' : ($cantidad <= 0 ? 'Agotado' : ($cantidad <= $minimo ? 'Bajo' : 'Disponible'))];
                } else {
                    $categorias[$categoria] ??= ['categoria' => $categoria, 'productos' => 0, 'cantidad' => 0, 'costo' => BigDecimal::zero(), 'venta' => BigDecimal::zero()];
                    $categorias[$categoria]['productos']++;
                    $categorias[$categoria]['cantidad'] += $cantidad;
                    foreach (['costo' => 'precio_costo', 'venta' => 'precio_venta'] as $columna => $campo) {
                        $categorias[$categoria][$columna] = $categorias[$categoria][$columna]->plus(BigDecimal::of((string) $producto[$campo])->multipliedBy($cantidad));
                    }
                }
            }
            if ($tipo === 'valoracion') {
                ksort($categorias);
                foreach ($categorias as $categoria) {
                    $categoria['margen'] = (string) $categoria['venta']->minus($categoria['costo'])->toScale(2);
                    $categoria['costo'] = (string) $categoria['costo']->toScale(2);
                    $categoria['venta'] = (string) $categoria['venta']->toScale(2);
                    yield $categoria;
                }
            }

            return;
        }
        $nombres = [];
        if (in_array($tipo, ['kardex', 'anulaciones'], true)) {
            $nombres = $this->sucursales->obtenerTodos()->mapWithKeys(fn ($s) => [(string) $s->getKey() => $s->nombre])->all();
        }
        $datos = match ($tipo) {
            'kardex' => $this->repository->kardex($f),'anulaciones' => $this->repository->anulaciones($f),default => $this->repository->agrupado($tipo, $f)
        };
        foreach ($datos as $fila) {
            if (isset($fila['centavos'])) {
                $centavos = BigDecimal::of((string) $fila['centavos']);
                $fila['total'] = (string) $centavos->dividedBy(100, 2, RoundingMode::HalfUp);
                if (isset($fila['documentos'])) {
                    $fila['promedio'] = (string) $centavos->dividedBy((int) $fila['documentos'] * 100, 2, RoundingMode::HalfUp);
                }
                unset($fila['centavos']);
            }
            if ($tipo === 'kardex') {
                $fila['sucursal'] = $nombres[$fila['sucursal_id']] ?? 'Sucursal no disponible';
                unset($fila['sucursal_id']);
            }
            if ($tipo === 'anulaciones') {
                $fila['sucursal'] = $fila['documento'] === 'traslados' ? ($fila['origen_nombre'].' → '.$fila['destino_nombre']) : ($fila['sucursal_nombre'] ?? $nombres[$fila['sucursal_id']] ?? 'Sucursal no disponible');
                if ($fila['documento'] === 'traslados') {
                    $fila['total'] = null;
                }
                unset($fila['_id'],$fila['sucursal_id'],$fila['sucursal_nombre'],$fila['origen_nombre'],$fila['destino_nombre']);
            }
            yield $fila;
        }
    }

    public function celdaCsv(mixed $valor, string $tipo): string
    {
        $texto = (string) ($valor ?? '');

        return $tipo === 'texto' && preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $texto) ? "'".$texto : $texto;
    }

    private function totales(string $tipo): array
    {
        $cantidad = ['Unidades', 'entero'];
        $documentos = ['Documentos', 'entero'];
        $total = ['Importe total', 'dinero'];

        return match ($tipo) {
            'existencias' => ['cantidad' => $cantidad],
            'valoracion' => ['cantidad' => $cantidad, 'costo' => ['Valor al costo', 'dinero'], 'venta' => ['Valor de venta', 'dinero'], 'margen' => ['Margen potencial', 'dinero']],
            'kardex' => ['entradas' => ['Entradas del período', 'entero'], 'salidas' => ['Salidas del período', 'entero'], 'variacion' => ['Variación del período', 'entero']],
            'productos-vendidos' => ['cantidad' => $cantidad, 'total' => $total],
            'flujo-traslados' => ['documentos' => $documentos, 'confirmadas' => ['Unidades confirmadas', 'entero'], 'pendientes' => ['Unidades pendientes', 'entero']],
            'anulaciones' => ['ventas' => ['Ventas anuladas', 'entero'], 'ingresos' => ['Ingresos anulados', 'entero'], 'traslados' => ['Traslados anulados', 'entero']],
            default => ['documentos' => $documentos, 'cantidad' => $cantidad, 'total' => $total],
        };
    }
}
