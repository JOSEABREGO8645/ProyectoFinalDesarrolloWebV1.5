<?php

namespace App\Repositories;

use App\Models\Sesion;

class SesionRepository
{
    public function crear(array $datos): Sesion
    {
        return Sesion::create($datos);
    }

    public function activa(string $id, int $ahora): ?Sesion
    {
        if (! preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return null;
        }

        return Sesion::where('_id', $id)->whereNull('revoked_at')->where('refresh_expires_at', '>', $ahora)->first();
    }

    public function rotar(string $id, string $hashAnterior, string $hashNuevo, int $ahora): bool
    {
        return Sesion::where('_id', $id)->where('refresh_hash', $hashAnterior)
            ->whereNull('revoked_at')->where('refresh_expires_at', '>', $ahora)
            ->update(['refresh_hash' => $hashNuevo, 'previous_refresh_hash' => $hashAnterior, 'rotated_at' => $ahora]) === 1;
    }

    public function revocar(string $id, int $ahora): void
    {
        Sesion::where('_id', $id)->whereNull('revoked_at')->update(['revoked_at' => $ahora]);
    }

    public function cambiarSucursal(string $id, string $anterior, string $siguiente, int $ahora): bool
    {
        return Sesion::where('_id', $id)->whereNull('revoked_at')->where('refresh_expires_at', '>', $ahora)
            ->where(fn ($q) => $q->where('sucursal_id', $anterior)->orWhereNull('sucursal_id'))
            ->update(['sucursal_id' => $siguiente]) === 1;
    }

    public function revocarPorUsuario(string $id, int $ahora): void
    {
        Sesion::where('usuario_id', $id)->whereNull('revoked_at')->update(['revoked_at' => $ahora]);
    }
}
