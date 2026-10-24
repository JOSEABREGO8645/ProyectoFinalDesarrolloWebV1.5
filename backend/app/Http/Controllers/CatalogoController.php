<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function __construct(private AuthService $auth, private ProveedorService $proveedores) {}

    public function sucursales(Request $request): JsonResponse
    {
        return response()->json($this->auth->sucursalesDisponibles($request->user()));
    }

    public function proveedores(): JsonResponse
    {
        return response()->json($this->proveedores->opciones());
    }
}
