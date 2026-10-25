<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutenticarJwt
{
    public function __construct(private AuthService $auth) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $datos = $this->auth->autenticar($request->bearerToken());
        if ($request->hasHeader('X-Sucursal-Id') && $request->header('X-Sucursal-Id') !== $datos['usuario']->sucursal_id) {
            return response()->json(['message' => 'La sucursal activa cambió. Actualice la vista antes de continuar.', 'code' => 'SUCURSAL_CAMBIADA'], 409)->header('Cache-Control', 'no-store');
        }
        $request->setUserResolver(fn () => $datos['usuario']);
        $request->attributes->set('auth_session_id', (string) $datos['sesion']->getKey());
        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
