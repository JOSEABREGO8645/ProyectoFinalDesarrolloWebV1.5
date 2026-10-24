<?php

use App\Http\Controllers\SucursalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Ruta para probar la conexión con MongoDB
Route::get('/ping', function (Request $request) {
    $connection = DB::connection('mongodb');

    try {
        $connection->command(['ping' => 1]);
        return response()->json([
            'ok' => true,
            'mensaje' => 'MongoDB conectado correctamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'ok' => false,
            'mensaje' => 'No fue posible conectar con MongoDB',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Rutas para el controlador de Sucursales
Route::get('/sucursales', [SucursalController::class, 'index']);
Route::post('/sucursales', [SucursalController::class, 'store']);
Route::get('/sucursales/buscar', [SucursalController::class, 'buscar']);
Route::get('/sucursales/{id}', [SucursalController::class, 'show']);
Route::put('/sucursales/{id}', [SucursalController::class, 'update']);
Route::delete('/sucursales/{id}', [SucursalController::class, 'destroy']);