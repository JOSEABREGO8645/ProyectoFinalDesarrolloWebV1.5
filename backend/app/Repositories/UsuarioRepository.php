<?php

namespace App\Repositories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;

class UsuarioRepository
{
    public function filtrar(array $filtros): Collection
    {
        $consulta = Usuario::query();
        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $regex = new Regex(preg_quote($texto), 'i');
            $consulta->where(fn ($query) => $query->where('nombre', 'regex', $regex)->orWhere('usuario', 'regex', $regex));
        }
        if (isset($filtros['activo'])) {
            $consulta->where('activo', (bool) $filtros['activo']);
        }
        if (! empty($filtros['rol'])) {
            $consulta->where('rol', $filtros['rol']);
        }

        return $consulta->orderBy($filtros['orden'] ?? 'nombre', $filtros['dir'] ?? 'asc')->orderBy('_id')->limit(500)->get();
    }

    public function porUsuario(string $usuario): ?Usuario
    {
        return Usuario::where('usuario', $usuario)->first();
    }

    public function encontrar(string $id): ?Usuario
    {
        return preg_match('/^[a-f0-9]{24}$/i', $id) ? Usuario::find($id) : null;
    }

    public function buscarPorId(string $id): Usuario
    {
        $usuario = $this->encontrar($id);
        abort_if($usuario === null, 404);

        return $usuario;
    }

    public function existeUsuario(string $usuario, ?string $exceptoId = null): bool
    {
        $consulta = Usuario::where('usuario', $usuario);
        if ($exceptoId !== null) {
            $consulta->where('_id', '!=', $exceptoId);
        }

        return $consulta->exists();
    }

    public function existeSuperusuario(): bool
    {
        return Usuario::where('rol', 'superusuario')->where('activo', true)->exists();
    }

    public function crear(array $datos): Usuario
    {
        Usuario::raw(fn ($coleccion) => $coleccion->createIndex(['usuario' => 1], ['unique' => true]));

        return Usuario::create($datos);
    }

    public function actualizar(string $id, array $datos, bool $revocar = false): Usuario
    {
        $usuario = $this->buscarPorId($id);
        if ($revocar) {
            Usuario::where('_id', $id)->increment('auth_version', 1, $datos);
        } else {
            $usuario->update($datos);
        }

        return $this->buscarPorId($id);
    }

    public function eliminar(string $id): void
    {
        $this->buscarPorId($id)->delete();
    }

    public function enUso(string $id): bool
    {
        foreach (['ventas', 'traslados', 'ingresos', 'movimientos'] as $coleccion) {
            if (DB::connection('mongodb')->table($coleccion)->where(fn ($q) => $q->where('usuario_id', $id)
                ->orWhere('anulado_por_id', $id)->orWhere('confirmado_por_id', $id))->exists()) {
                return true;
            }
        }

        return false;
    }
}
