<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioBuscarRequest;
use App\Http\Requests\UsuarioRequest;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $service) {}

    public function index(UsuarioBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated()));
    }

    public function buscar(UsuarioBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->validated()));
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Usuario creado correctamente.', 'datos' => $this->service->agregar($request->validated())], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->service->buscar($id));
    }

    public function update(UsuarioRequest $request, string $id): JsonResponse
    {
        return response()->json(['mensaje' => 'Cambios guardados correctamente.', 'datos' => $this->service->editar($id, $request->validated(), $request->user())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->eliminar($id, $request->user());

        return response()->json(['mensaje' => 'Usuario eliminado correctamente.']);
    }
}
