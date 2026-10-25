<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoCambioRequest;
use App\Services\IntegracionService;
use App\Services\TipoCambioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegracionController extends Controller
{
    public function __construct(private IntegracionService $service, private TipoCambioService $cambio) {}

    public function factura(Request $request, string $id): JsonResponse
    {
        return response()->json($this->service->generar('ventas', $id, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function pago(Request $request, string $id): JsonResponse
    {
        return response()->json($this->service->generar('ingresos', $id, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function tipoCambio(TipoCambioRequest $request): JsonResponse
    {
        return response()->json($this->cambio->consultar(isset($request->validated()['monto_usd']) ? (string) $request->validated('monto_usd') : null))->header('Cache-Control', 'private, no-store');
    }
}
