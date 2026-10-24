<?php

namespace App\Http\Middleware;

use App\Services\PermisoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Modulo
{
    public function __construct(private PermisoService $permisos) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$modulos): Response
    {
        foreach ($modulos as $modulo) {
            if ($this->permisos->puede($request->user(), $modulo)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No tiene permiso para este módulo.'], 403);
    }
}
