<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerificarCodigoRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function __construct(private AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->respuestaSesion($this->service->login($request->validated()));
    }

    public function refresh(Request $request): JsonResponse
    {
        return $this->respuestaSesion($this->service->renovar($request->cookie(config('jwt.cookie'))));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->service->perfil($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->bearerToken(), $request->cookie(config('jwt.cookie')));

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.'])
            ->withCookie($this->cookie('', 1))->header('Cache-Control', 'no-store');
    }

    public function verificarCodigo(VerificarCodigoRequest $request): JsonResponse
    {
        $this->service->verificarCodigo($request->user(), (string) $request->validated('codigo'));

        return response()->json(['ok' => true]);
    }

    private function respuestaSesion(array $datos): JsonResponse
    {
        $cookie = $this->cookie($datos['refresh_token'], now()->timestamp + $datos['refresh_expires_in']);
        unset($datos['refresh_token'], $datos['refresh_expires_in']);

        return response()->json($datos)->withCookie($cookie)->header('Cache-Control', 'no-store');
    }

    private function cookie(string $valor, int $expira): Cookie
    {
        return new Cookie(
            name: config('jwt.cookie'), value: $valor, expire: $expira,
            path: config('jwt.cookie_path'), secure: (bool) config('jwt.cookie_secure'),
            httpOnly: true, sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
