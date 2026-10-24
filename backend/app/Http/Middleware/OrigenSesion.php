<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrigenSesion
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origen = $request->header('Origin');
        if ($request->header('X-Requested-With') !== 'XMLHttpRequest'
            || ($origen !== null && ! in_array($origen, config('jwt.origins'), true))) {
            return response()->json(['message' => 'Origen de la solicitud no permitido.'], 403);
        }
        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
