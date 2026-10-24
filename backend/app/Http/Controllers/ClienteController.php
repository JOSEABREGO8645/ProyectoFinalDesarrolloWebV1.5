<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogoBuscarRequest;
use App\Http\Requests\ClienteRequest;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    public function __construct(private ClienteService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->listar());
    }

    public function buscar(CatalogoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated()));
    }

    public function store(ClienteRequest $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Registro creado correctamente.', 'datos' => $this->service->agregar($request->validated())], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->buscar($id));
    }

    public function update(ClienteRequest $request, string $id): JsonResponse
    {
        return response()->json(['mensaje' => 'Cambios guardados correctamente.', 'datos' => $this->service->editar($id, $request->validated())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->eliminar($id);

        return response()->json(['mensaje' => 'Registro eliminado correctamente.']);
    }

    public function porNit(string $nit): JsonResponse
    {
        return response()->json($this->service->porNit($nit));
    }
}
