<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Repositories\DashboardRepository;
use App\Repositories\SucursalRepository;
use Carbon\CarbonImmutable;
use MongoDB\BSON\UTCDateTime;

class DashboardService
{
    public function __construct(private DashboardRepository $repository, private SucursalRepository $sucursales, private PermisoService $permisos) {}

    public function resumen(array $filtros, Usuario $usuario): array
    {
        $sucursal = $filtros['sucursal_id'] ?? null;
        if ($usuario->rol !== 'superusuario') {
            if ($sucursal !== null && $sucursal !== $usuario->sucursal_id) {
                throw new ReglaNegocioException('No tiene permiso para consultar otra sucursal.', 403);
            }
            $sucursal = $usuario->sucursal_id;
        }
        $nombre = $sucursal !== null ? $this->sucursales->buscarPorId($sucursal)->nombre : 'Todas las sucursales';
        $zona = config('dashboard.timezone');
        $hasta = isset($filtros['hasta']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filtros['hasta'], $zona) : CarbonImmutable::now($zona)->startOfDay();
        $desde = isset($filtros['desde']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filtros['desde'], $zona) : $hasta->subDays(6);
        if ($desde->diffInDays($hasta) > 89) {
            throw new ReglaNegocioException('Seleccione un período de hasta 90 días.');
        }
        $resultado = ['sucursal_id' => $sucursal, 'sucursal_nombre' => $nombre, 'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'zona' => $zona], 'actualizado_at' => now()->toIso8601String()];
        if ($this->permisos->puede($usuario, 'catalogo')) {
            $inventario = ['activos' => 0, 'inactivos' => 0, 'unidades' => 0, 'agotados' => 0, 'bajos' => 0, 'alertas' => []];
            foreach ($this->repository->inventario($sucursal) as $producto) {
                $cantidad = (int) $producto['existencia'];
                $inventario['unidades'] += $cantidad;
                if (! ($producto['activo'] ?? false)) {
                    $inventario['inactivos']++;

                    continue;
                }
                $inventario['activos']++;
                if ($cantidad <= 0 || $cantidad <= (int) $producto['stock_minimo']) {
                    $agotado = $cantidad <= 0;
                    $inventario[$agotado ? 'agotados' : 'bajos']++;
                    $inventario['alertas'][] = ['_id' => (string) $producto['_id'], 'codigo' => $producto['codigo'], 'nombre' => $producto['nombre'],
                        'cantidad' => $cantidad, 'minimo' => (int) $producto['stock_minimo'], 'estado' => $agotado ? 'Agotado' : 'Existencia baja'];
                }
            }
            usort($inventario['alertas'], fn ($a, $b) => [$a['cantidad'], $a['nombre']] <=> [$b['cantidad'], $b['nombre']]);
            $inventario['alertas'] = array_slice($inventario['alertas'], 0, 10);
            $resultado['inventario'] = $inventario;
        }
        foreach (['ventas' => 'emitida', 'ingresos' => 'registrado', 'traslados' => 'confirmado'] as $tipo => $estado) {
            if (! $this->permisos->puede($usuario, $tipo)) {
                continue;
            }
            $datos = $this->repository->documentos($tipo, $sucursal, new UTCDateTime($desde->utc()), new UTCDateTime($hasta->addDay()->utc()), $zona);
            $estados = array_column($datos['estados'], null, '_id');
            $actual = $estados[$estado] ?? [];
            $resumen = ['documentos' => (int) ($actual['cantidad'] ?? 0), 'total_centavos' => (int) ($actual['centavos'] ?? 0),
                'unidades' => (int) ($actual['unidades'] ?? 0), 'anulados' => (int) ($estados['anulado']['cantidad'] ?? 0), 'recientes' => $datos['recientes']];
            if ($tipo === 'ventas') {
                $dias = [];
                foreach ($datos['dias'] as $dia) {
                    if ($dia['_id']['estado'] === 'emitida') {
                        $dias[$dia['_id']['fecha']] = $dia;
                    }
                }
                $resumen['diarias'] = [];
                for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
                    $fecha = $dia->toDateString();
                    $resumen['diarias'][] = ['fecha' => $fecha, 'centavos' => (int) ($dias[$fecha]['centavos'] ?? 0), 'documentos' => (int) ($dias[$fecha]['cantidad'] ?? 0)];
                }
            }
            if ($tipo === 'traslados') {
                $resumen['pendientes'] = $this->repository->pendientes($sucursal);
            }
            $resultado[$tipo] = $resumen;
        }

        return $resultado;
    }
}
