<?php

namespace App\Services;

use App\Models\Usuario;

class PermisoService
{
    public function puede(?Usuario $usuario, string $modulo): bool
    {
        if ($usuario === null || ! $usuario->activo || ! in_array($modulo, [...config('permisos.modulos'), 'usuarios'], true)) {
            return false;
        }
        if ($usuario->rol === 'superusuario') {
            return true;
        }

        return $usuario->rol === 'empleado' && $modulo !== 'usuarios' && in_array($modulo, (array) $usuario->modulos, true);
    }
}
