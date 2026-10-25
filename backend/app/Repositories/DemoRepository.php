<?php

namespace App\Repositories;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Traslado;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\BulkWriteException;

class DemoRepository
{
    private const MARCA = 'demostracion:v1';

    private function modelos(): array
    {
        return ['sucursales' => Sucursal::class, 'proveedores' => Proveedor::class, 'clientes' => Cliente::class, 'productos' => Producto::class, 'usuarios' => Usuario::class,
            'ingresos' => Ingreso::class, 'ventas' => Venta::class, 'traslados' => Traslado::class, 'existencias' => Existencia::class, 'movimientos' => Movimiento::class];
    }

    private function marca(): ?array
    {
        $dato = DB::connection('mongodb')->getCollection('contadores')->findOne(['_id' => self::MARCA]);

        return $dato === null ? null : (array) $dato;
    }

    public function validar(string $base, string $huella): void
    {
        if (DB::connection('mongodb')->getMongoDB()->getDatabaseName() !== $base) {
            throw new ReglaNegocioException('La conexión no apunta a la base de demostración solicitada.');
        }
        $marca = $this->marca();
        if ($marca !== null) {
            if (($marca['huella'] ?? null) !== $huella) {
                throw new ReglaNegocioException('La base pertenece a otra preparación. Conserve su archivo original de accesos.');
            }

            return;
        }
        foreach ($this->modelos() as $modelo) {
            if ($modelo::exists()) {
                throw new ReglaNegocioException('La base contiene datos sin una marca de demostración válida. No se modificó.');
            }
        }
        foreach (['auth_sessions', 'contadores'] as $coleccion) {
            if (DB::connection('mongodb')->table($coleccion)->exists()) {
                throw new ReglaNegocioException('La base contiene información ajena a esta demostración.');
            }
        }
    }

    public function reservar(string $huella, string $token): bool
    {
        $coleccion = DB::connection('mongodb')->getCollection('contadores');
        $marca = $this->marca();
        if (($marca['estado'] ?? null) === 'completada') {
            if (($marca['huella'] ?? null) !== $huella) {
                throw new ReglaNegocioException('La base pertenece a otra preparación. Conserve sus accesos originales.');
            }

            return false;
        }
        $reserva = ['huella' => $huella, 'token' => $token, 'estado' => 'cargando', 'vence' => now()->timestamp + 600];
        if ($marca === null) {
            try {
                $coleccion->insertOne(array_merge(['_id' => self::MARCA], $reserva));

                return true;
            } catch (BulkWriteException $e) {
                if ($e->getCode() !== 11000) {
                    throw $e;
                }
            }
        } else {
            $resultado = $coleccion->updateOne(['_id' => self::MARCA, 'huella' => $huella, 'vence' => ['$lte' => now()->timestamp]], ['$set' => $reserva]);
            if ($resultado->getModifiedCount() === 1) {
                return true;
            }
        }
        throw new ReglaNegocioException('Otra preparación está en curso. Espere y vuelva a ejecutar el comando.', 409);
    }

    public function terminar(string $token, bool $completada): void
    {
        $r = DB::connection('mongodb')->getCollection('contadores')->updateOne(['_id' => self::MARCA, 'token' => $token], ['$set' => ['estado' => $completada ? 'completada' : 'interrumpida', 'vence' => 0]]);
        if ($r->getMatchedCount() !== 1) {
            throw new ReglaNegocioException('Se perdió la reserva de preparación. Vuelva a intentar.', 409);
        }
    }

    public function encontrar(string $tipo, string $campo, string $valor): mixed
    {
        return $this->modelos()[$tipo]::where($campo, $valor)->first();
    }

    public function resumen(): array
    {
        $resumen = [];
        foreach ($this->modelos() as $nombre => $modelo) {
            $resumen[$nombre] = $modelo::count();
        }
        $resumen['unidades'] = (int) Existencia::sum('cantidad');
        $resumen['unidades_en_transito'] = (int) Traslado::where('estado', 'enviado')->get()->sum(fn ($t) => array_sum(array_column($t->items, 'cantidad')));

        return $resumen;
    }
}
