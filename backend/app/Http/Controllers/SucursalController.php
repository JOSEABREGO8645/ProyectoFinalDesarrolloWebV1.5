<?php

namespace App\Http\Controllers;

use App\Http\Requests\SucursalRequest;
use App\Services\SucursalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    public function __construct(
        private SucursalService $service
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->listar());
    }

    public function buscar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'q' => 'nullable|string|max:100',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|string|in:nombre,direccion,telefono,created_at',
            'dir' => 'nullable|string|in:asc,desc',
        ]);

        return response()->json($this->service->filtrar($filtros));
    }

    public function store(SucursalRequest $request): JsonResponse
    {
        $sucursal = $this->service->agregar($request->validated());

        return response()->json([
            'mensaje' => 'Sucursal creada correctamente.',
            'datos' => $sucursal
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->buscar($id));
    }

    public function update(SucursalRequest $request, string $id): JsonResponse
    {
        $sucursal = $this->service->editar($id, $request->validated());

        return response()->json([
            'mensaje' => 'Sucursal actualizada correctamente.',
            'datos' => $sucursal
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->eliminar($id);

        return response()->json([
            'mensaje' => 'Sucursal eliminada correctamente.'
        ]);
    }
}