<?php

namespace App\Repositories;

use App\Models\Ingreso;
use App\Models\Venta;

class IntegracionRepository
{
    private function modelo(string $tipo): string
    {
        return match ($tipo) {
            'ventas' => Venta::class,'ingresos' => Ingreso::class
        };
    }

    public function reservar(string $tipo, string $id, string $campo, string $token, int $ahora, int $vence, string $usuario): bool
    {
        return $this->modelo($tipo)::where('_id', $id)->where('estado', $tipo === 'ventas' ? 'emitida' : 'registrado')
            ->where('integraciones.'.$campo.'.estado', '!=', 'completada')
            ->where(fn ($q) => $q->whereNull('api_en_curso.'.$campo)->orWhere('api_en_curso.'.$campo.'.vence', '<=', $ahora))
            ->update(['api_en_curso.'.$campo => ['token' => $token, 'vence' => $vence],
                'integraciones.'.$campo => ['estado' => 'procesando', 'simulado' => true, 'solicitado_por_id' => $usuario]]) === 1;
    }

    public function guardar(string $tipo, string $id, string $campo, string $token, array $resultado): bool
    {
        return $this->modelo($tipo)::where('_id', $id)->where('api_en_curso.'.$campo.'.token', $token)
            ->update(['integraciones.'.$campo => $resultado, 'api_en_curso.'.$campo => null]) === 1;
    }
}
