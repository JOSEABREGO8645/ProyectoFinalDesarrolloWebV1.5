<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoBuscarRequest;
use App\Http\Requests\ProductoRequest;
use App\Services\ProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(private ProductoService $service) {}

    public function index(ProductoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated(), $request->user()));
    }

    public function buscar(ProductoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated(), $request->user()));
    }

    public function store(ProductoRequest $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Producto creado correctamente.', 'datos' => $this->service->agregar($request->validated())], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->service->buscar($id, $request->user()));
    }

    public function update(ProductoRequest $request, string $id): JsonResponse
    {
        return response()->json(['mensaje' => 'Cambios guardados correctamente.', 'datos' => $this->service->editar($id, $request->validated())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->eliminar($id);

        return response()->json(['mensaje' => 'Producto eliminado correctamente.']);
    }
}
