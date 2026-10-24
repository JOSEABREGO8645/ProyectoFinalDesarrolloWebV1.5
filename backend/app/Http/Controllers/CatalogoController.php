<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperacionConsultaRequest;
use App\Http\Requests\ProductoBuscarRequest;
use App\Services\AuthService;
use App\Services\CatalogoOperacionService;
use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function __construct(private AuthService $auth, private ProveedorService $proveedores, private CatalogoOperacionService $operaciones) {}

    public function productos(ProductoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->operaciones->productos($request->validated(), $request->user()));
    }

    public function destinos(): JsonResponse
    {
        return response()->json($this->operaciones->destinos());
    }

    public function clientes(OperacionConsultaRequest $request): JsonResponse
    {
        return response()->json($this->operaciones->clientes($request->validated()));
    }

    public function sucursales(Request $request): JsonResponse
    {
        return response()->json($this->auth->sucursalesDisponibles($request->user()));
    }

    public function proveedores(): JsonResponse
    {
        return response()->json($this->proveedores->opciones());
    }
}
