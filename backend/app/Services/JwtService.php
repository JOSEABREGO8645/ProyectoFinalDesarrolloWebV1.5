<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use UnexpectedValueException;

class JwtService
{
    public function emitir(string $usuarioId, string $sesionId): string
    {
        $ahora = now()->timestamp;

        return JWT::encode([
            'iss' => config('jwt.issuer'), 'aud' => config('jwt.audience'),
            'sub' => $usuarioId, 'sid' => $sesionId,
            'jti' => bin2hex(random_bytes(16)), 'token_use' => 'access',
            'iat' => $ahora, 'nbf' => $ahora,
            'exp' => $ahora + $this->duracion(),
        ], $this->clave(), 'HS256');
    }

    public function validar(string $token): array
    {
        $clave = $this->clave();
        try {
            if (strlen($token) > 4096) {
                throw new UnexpectedValueException;
            }
            $datos = (array) JWT::decode($token, new Key($clave, 'HS256'));
            if (($datos['iss'] ?? null) !== config('jwt.issuer') || ($datos['aud'] ?? null) !== config('jwt.audience')
                || ($datos['token_use'] ?? null) !== 'access'
                || ! is_string($datos['sub'] ?? null) || ! preg_match('/^[a-f0-9]{24}$/', $datos['sub'])
                || ! is_string($datos['sid'] ?? null) || ! preg_match('/^[a-f0-9]{24}$/', $datos['sid'])
                || ! is_int($datos['exp'] ?? null) || ! is_int($datos['iat'] ?? null) || ! is_int($datos['nbf'] ?? null)
                || $datos['iat'] > now()->timestamp || $datos['exp'] <= now()->timestamp) {
                throw new UnexpectedValueException;
            }

            return $datos;
        } catch (UnexpectedValueException|\DomainException $e) {
            throw new ReglaNegocioException('La sesión no es válida o ha expirado. Inicie sesión nuevamente.', 401);
        }
    }

    public function duracion(): int
    {
        return max(1, (int) config('jwt.access_minutes')) * 60;
    }

    private function clave(): string
    {
        $clave = (string) config('jwt.secret');
        if (strlen($clave) < 32) {
            throw new LogicException('Configure JWT_SECRET con una clave aleatoria de al menos 32 caracteres.');
        }

        return $clave;
    }
}
