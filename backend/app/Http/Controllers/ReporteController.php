<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoBuscarRequest;
use App\Http\Requests\ReporteRequest;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->catalogo());
    }

    public function productos(ProductoBuscarRequest $request): JsonResponse
    {
        return response()->json($this->service->productos($request->validated()));
    }

    public function show(ReporteRequest $request, string $tipo): JsonResponse|StreamedResponse
    {
        $contexto = $this->service->preparar($tipo, $request->validated(), $request->user());
        if ($request->validated('formato') !== 'csv') {
            return response()->json($this->service->consultar($contexto))->header('Cache-Control', 'private, no-store');
        }

        return response()->streamDownload(function () use ($contexto) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            $columnas = $contexto['definicion']['columnas'];
            fputcsv($salida, array_column($columnas, 'label'), ',', '"', '', "\r\n");
            foreach ($this->service->filas($contexto) as $fila) {
                $valores = [];
                foreach ($columnas as $columna) {
                    $valores[] = $this->service->celdaCsv($fila[$columna['key']] ?? null, $columna['tipo']);
                }
                fputcsv($salida, $valores, ',', '"', '', "\r\n");
            }
            fclose($salida);
        }, 'reporte-'.$tipo.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
