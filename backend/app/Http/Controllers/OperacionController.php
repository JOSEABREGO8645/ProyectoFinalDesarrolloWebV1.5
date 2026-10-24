<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperacionAccionRequest;
use App\Http\Requests\OperacionConsultaRequest;
use App\Http\Requests\OperacionRequest;
use App\Services\OperacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperacionController extends Controller
{
    public function __construct(private OperacionService $service) {}

    public function index(OperacionConsultaRequest $request): JsonResponse
    {
        return response()->json($this->service->filtrar($request->route('tipo'), $request->validated(), $request->user()));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->service->buscar($request->route('tipo'), $id, $request->user()));
    }

    public function store(OperacionRequest $request): JsonResponse
    {
        return response()->json($this->service->crear($request->route('tipo'), $request->validated(), $request->user()), 201);
    }

    public function finalizar(OperacionAccionRequest $request, string $id): JsonResponse
    {
        return response()->json($this->service->finalizar($request->route('tipo'), $id, $request->route('accion'), $request->validated('motivo'), $request->user()));
    }

    public function kardex(OperacionConsultaRequest $request): JsonResponse
    {
        return response()->json($this->service->kardex($request->validated(), $request->user()));
    }
}
