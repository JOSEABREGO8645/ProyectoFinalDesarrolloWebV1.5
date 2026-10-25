<?php

namespace App\Repositories;

use App\Models\DocumentoInventario;
use App\Models\Ingreso;
use App\Models\Traslado;
use App\Models\Venta;
use Closure;
use Illuminate\Support\Facades\DB;
use MongoDB\Operation\FindOneAndUpdate;
use Throwable;

class OperacionRepository
{
    public function preparado(): bool
    {
        return DB::connection('mongodb')->table('migrations')->where('migration', '2026_10_23_232450_crear_indices_inventario')->exists();
    }

    public function enTransaccion(): bool
    {
        return DB::connection('mongodb')->getSession()?->isInTransaction() ?? false;
    }

    private function modelo(string $tipo): string
    {
        return match ($tipo) {
            'ingresos' => Ingreso::class, 'ventas' => Venta::class, 'traslados' => Traslado::class
        };
    }

    public function atomica(Closure $accion): mixed
    {
        $conexion = DB::connection('mongodb');
        try {
            return $conexion->transaction($accion, 5);
        } catch (Throwable $error) {
            // El driver aborta la sesión; también restablecemos el contador de Laravel.
            if ($conexion->transactionLevel() > 0) {
                $conexion->rollBack();
            }
            throw $error;
        }
    }

    public function buscar(string $tipo, string $id): DocumentoInventario
    {
        abort_unless(preg_match('/^[a-f0-9]{24}$/i', $id), 404);

        return $this->modelo($tipo)::findOrFail($id);
    }

    public function porOperacion(string $tipo, string $clave): ?DocumentoInventario
    {
        return $this->modelo($tipo)::where('operacion_id', $clave)->first();
    }

    public function crear(string $tipo, array $datos): DocumentoInventario
    {
        return $this->modelo($tipo)::create($datos);
    }

    public function cambiarEstado(string $tipo, string $id, string $estado, array $datos): bool
    {
        return $this->modelo($tipo)::where('_id', $id)->where('estado', $estado)->update($datos) === 1;
    }

    public function filtrar(string $tipo, array $filtros, ?string $sucursal): array
    {
        $consulta = $this->modelo($tipo)::query();
        if (! empty($filtros['operacion_id'])) {
            $consulta->where('operacion_id', $filtros['operacion_id']);
        }
        if ($sucursal !== null) {
            if ($tipo === 'traslados') {
                $consulta->where(fn ($q) => $q->where('origen_id', $sucursal)->orWhere('destino_id', $sucursal));
            } else {
                $consulta->where('sucursal_id', $sucursal);
            }
        }
        if (! empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        return $consulta->orderByDesc('created_at')->orderByDesc('_id')->limit(200)->get()->toArray();
    }

    public function siguiente(string $clave): int
    {
        $conexion = DB::connection('mongodb');
        $doc = $conexion->getCollection('contadores')->findOneAndUpdate(
            ['_id' => $clave], ['$inc' => ['valor' => 1]],
            ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER, 'session' => $conexion->getSession()],
        );

        return (int) $doc['valor'];
    }
}
