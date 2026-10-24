<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index(DashboardRequest $request): JsonResponse
    {
        return response()->json($this->service->resumen($request->validated(), $request->user()));
    }
}
