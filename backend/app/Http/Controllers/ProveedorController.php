<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogoBuscarRequest;
use App\Http\Requests\ProveedorRequest;
use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;

class ProveedorController extends Controller
{
    public function __construct(private ProveedorService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->listar());
    }

    public function buscar(CatalogoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated()));
    }

    public function store(ProveedorRequest $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Registro creado correctamente.', 'datos' => $this->service->agregar($request->validated())], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->buscar($id));
    }

    public function update(ProveedorRequest $request, string $id): JsonResponse
    {
        return response()->json(['mensaje' => 'Cambios guardados correctamente.', 'datos' => $this->service->editar($id, $request->validated())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->eliminar($id);

        return response()->json(['mensaje' => 'Registro eliminado correctamente.']);
    }
}
