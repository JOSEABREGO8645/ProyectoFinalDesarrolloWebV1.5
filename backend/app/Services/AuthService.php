<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Repositories\SesionRepository;
use App\Repositories\SucursalRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private UsuarioRepository $usuarios,
        private SesionRepository $sesiones,
        private SucursalRepository $sucursales,
        private JwtService $jwt,
    ) {}

    public function login(array $datos): array
    {
        $usuario = $this->usuarios->porUsuario(mb_strtolower(trim($datos['usuario'])));
        $hash = $usuario?->password ?? '$2y$12$yLQvcZdLKbn.uQPfvqvOgOYruiqRTn8yDrX9lUagHPxsxtzCsd/va';
        $claveCorrecta = Hash::check($datos['password'], $hash);
        if (! $claveCorrecta || ! $this->habilitado($usuario)) {
            throw new ReglaNegocioException('Usuario o contraseña incorrectos.', 401);
        }
        $secreto = bin2hex(random_bytes(32));
        $sesion = $this->sesiones->crear([
            'usuario_id' => (string) $usuario->getKey(),
            'sucursal_id' => $this->sucursalInicial($usuario),
            'auth_version' => (int) $usuario->auth_version,
            'refresh_hash' => hash('sha256', $secreto),
            'refresh_expires_at' => now()->addDays(max(1, (int) config('jwt.refresh_days')))->timestamp,
            'revoked_at' => null,
        ]);

        return $this->respuesta($usuario, $sesion, $secreto);
    }

    public function renovar(?string $token): array
    {
        [$id, $secreto] = $this->separarRefresh($token);
        $sesion = $this->sesiones->activa($id, now()->timestamp);
        if ($sesion === null) {
            $this->rechazarSesion();
        }
        $hash = hash('sha256', $secreto);
        if (! hash_equals($sesion->refresh_hash, $hash)) {
            if (is_string($sesion->previous_refresh_hash) && hash_equals($sesion->previous_refresh_hash, $hash)) {
                $this->sesiones->revocar($id, now()->timestamp);
            }
            $this->rechazarSesion();
        }
        $usuario = $this->usuarios->encontrar($sesion->usuario_id);
        if (! $this->habilitado($usuario) || (int) $sesion->auth_version !== (int) $usuario->auth_version) {
            $this->sesiones->revocar($id, now()->timestamp);
            $this->rechazarSesion();
        }
        $nuevo = bin2hex(random_bytes(32));
        if (! $this->sesiones->rotar($id, $hash, hash('sha256', $nuevo), now()->timestamp)) {
            $this->rechazarSesion();
        }

        return $this->respuesta($usuario, $sesion, $nuevo);
    }

    public function autenticar(?string $token): array
    {
        if ($token === null || $token === '') {
            $this->rechazarSesion();
        }
        $datos = $this->jwt->validar($token);
        $sesion = $this->sesiones->activa($datos['sid'], now()->timestamp);
        if ($sesion === null || $sesion->usuario_id !== $datos['sub']) {
            $this->rechazarSesion();
        }
        $usuario = $this->usuarios->encontrar($datos['sub']);
        if (! $this->habilitado($usuario) || (int) $sesion->auth_version !== (int) $usuario->auth_version) {
            $this->rechazarSesion();
        }

        return ['usuario' => $this->contextualizar($usuario, $sesion), 'sesion' => $sesion];
    }

    public function logout(?string $accessToken, ?string $refreshToken): void
    {
        if ($accessToken !== null) {
            try {
                $datos = $this->autenticar($accessToken);
                $this->sesiones->revocar((string) $datos['sesion']->getKey(), now()->timestamp);
            } catch (ReglaNegocioException $e) {
                // El refresh permite cerrar una sesión aunque el access token ya expiró.
            }
        }
        try {
            [$id, $secreto] = $this->separarRefresh($refreshToken);
            $sesion = $this->sesiones->activa($id, now()->timestamp);
            if ($sesion !== null && hash_equals($sesion->refresh_hash, hash('sha256', $secreto))) {
                $this->sesiones->revocar($id, now()->timestamp);
            }
        } catch (ReglaNegocioException $e) {
            // Cerrar sesión también es válido si la cookie ya no existe o expiró.
        }
    }

    public function perfil(Usuario $usuario): array
    {
        return [
            '_id' => (string) $usuario->getKey(), 'id' => (string) $usuario->getKey(),
            'nombre' => $usuario->nombre, 'usuario' => $usuario->usuario,
            'rol' => $usuario->rol,
            'modulos' => $usuario->rol === 'superusuario' ? config('permisos.modulos') : array_values((array) $usuario->modulos),
            'sucursal_id' => $usuario->sucursal_id,
            'sucursal_principal_id' => $usuario->getRawOriginal('sucursal_id') ?? $usuario->sucursal_id,
            'sucursal_ids' => $usuario->sucursalesAsignadas(),
            'sucursales' => array_values(array_filter($this->sucursalesDisponibles($usuario), fn ($s) => $s['activo'])),
            'sucursal_nombre' => $this->sucursales->encontrarActiva($usuario->sucursal_id)?->nombre,
            'tiene_codigo' => (bool) $usuario->codigo_hash,
        ];
    }

    public function verificarCodigo(Usuario $usuario, string $codigo): void
    {
        if (! $usuario->codigo_hash || ! Hash::check($codigo, $usuario->codigo_hash)) {
            throw new ReglaNegocioException('Código de facturación incorrecto o no configurado.');
        }
    }

    public function sucursalesDisponibles(Usuario $usuario): array
    {
        $sucursales = $usuario->rol === 'superusuario'
            ? $this->sucursales->obtenerTodos()
            : $this->sucursales->activasPorIds($usuario->sucursalesAsignadas());

        return $sucursales->map(fn ($sucursal) => ['_id' => (string) $sucursal->getKey(), 'nombre' => $sucursal->nombre, 'activo' => (bool) $sucursal->activo])->values()->all();
    }

    private function habilitado(?Usuario $usuario): bool
    {
        return $usuario !== null && $usuario->activo && in_array($usuario->rol, ['empleado', 'superusuario'], true)
            && count(array_filter($this->sucursalesDisponibles($usuario), fn ($s) => $s['activo'])) > 0;
    }

    private function respuesta(Usuario $usuario, Sesion $sesion, string $secreto): array
    {
        $usuario = $this->contextualizar($usuario, $sesion);

        return [
            'access_token' => $this->jwt->emitir((string) $usuario->getKey(), (string) $sesion->getKey()),
            'token_type' => 'Bearer', 'expires_in' => $this->jwt->duracion(),
            'usuario' => $this->perfil($usuario),
            'refresh_token' => (string) $sesion->getKey().'.'.$secreto,
            'refresh_expires_in' => max(0, $sesion->refresh_expires_at - now()->timestamp),
        ];
    }

    public function cambiarSucursal(Usuario $usuario, string $sesionId, string $sucursalId): array
    {
        $opciones = array_column(array_filter($this->sucursalesDisponibles($usuario), fn ($s) => $s['activo']), '_id');
        if (! in_array($sucursalId, $opciones, true)) {
            throw new ReglaNegocioException('No tiene acceso a esa sucursal o está inactiva.', 403);
        }
        if ($sucursalId !== $usuario->sucursal_id && ! $this->sesiones->cambiarSucursal($sesionId, $usuario->sucursal_id, $sucursalId, now()->timestamp)) {
            throw new ReglaNegocioException('La sesión cambió mientras seleccionaba la sucursal. Actualice e intente de nuevo.', 409);
        }
        $usuario->setAttribute('sucursal_id', $sucursalId);

        return ['access_token' => $this->jwt->emitir((string) $usuario->getKey(), $sesionId),
            'token_type' => 'Bearer', 'expires_in' => $this->jwt->duracion(), 'usuario' => $this->perfil($usuario)];
    }

    private function sucursalInicial(Usuario $usuario): string
    {
        $activas = array_values(array_filter($this->sucursalesDisponibles($usuario), fn ($s) => $s['activo']));
        $ids = array_column($activas, '_id');

        return in_array($usuario->sucursal_id, $ids, true) ? $usuario->sucursal_id : $ids[0];
    }

    private function contextualizar(Usuario $usuario, Sesion $sesion): Usuario
    {
        $id = $sesion->sucursal_id ?? $usuario->sucursal_id;
        $ids = array_column(array_filter($this->sucursalesDisponibles($usuario), fn ($s) => $s['activo']), '_id');
        if (! in_array($id, $ids, true)) {
            $this->sesiones->revocar((string) $sesion->getKey(), now()->timestamp);
            $this->rechazarSesion();
        }
        $usuario->setAttribute('sucursal_id', $id);

        return $usuario;
    }

    private function separarRefresh(?string $token): array
    {
        if ($token === null || ! preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $token, $partes)) {
            $this->rechazarSesion();
        }

        return [$partes[1], $partes[2]];
    }

    private function rechazarSesion(): never
    {
        throw new ReglaNegocioException('La sesión no es válida o ha expirado. Inicie sesión nuevamente.', 401);
    }
}
