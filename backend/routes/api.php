<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegracionController;
use App\Http\Controllers\OperacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
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
            'mensaje' => 'MongoDB conectado correctamente',
        ]);
    } catch (Exception $e) {
        return response()->json([
            'ok' => false,
            'mensaje' => 'No fue posible conectar con MongoDB',
        ], 500);
    }
});

Route::prefix('auth')->middleware('origen.sesion')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:auth-refresh');
});

Route::middleware('auth.jwt')->group(function () {
    Route::post('/ventas/{id}/factura-prueba', [IntegracionController::class, 'factura'])->middleware(['modulo:ventas', 'throttle:integraciones']);
    Route::post('/ingresos/{id}/pago-prueba', [IntegracionController::class, 'pago'])->middleware(['modulo:ingresos', 'throttle:integraciones']);
    Route::get('/integraciones/tipo-cambio', [IntegracionController::class, 'tipoCambio'])->middleware(['modulo:catalogo,ventas,ingresos', 'throttle:integraciones']);
    Route::get('/reportes', [ReporteController::class, 'index'])->middleware('modulo:reportes');
    Route::get('/reportes/productos', [ReporteController::class, 'productos'])->middleware('modulo:reportes');
    Route::get('/reportes/{tipo}', [ReporteController::class, 'show'])->middleware('modulo:reportes');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('modulo:catalogo,ventas,ingresos,traslados');
    foreach (['ingresos', 'ventas', 'traslados'] as $tipo) {
        Route::get('/'.$tipo, [OperacionController::class, 'index'])->defaults('tipo', $tipo)->middleware('modulo:'.$tipo);
        Route::post('/'.$tipo, [OperacionController::class, 'store'])->defaults('tipo', $tipo)
            ->middleware($tipo === 'ventas' ? ['modulo:ventas', 'throttle:codigo'] : ['modulo:'.$tipo]);
        Route::get('/'.$tipo.'/{id}', [OperacionController::class, 'show'])->defaults('tipo', $tipo)->middleware('modulo:'.$tipo);
        Route::post('/'.$tipo.'/{id}/anular', [OperacionController::class, 'finalizar'])->defaults('tipo', $tipo)->defaults('accion', 'anular')->middleware('modulo:'.$tipo);
    }
    Route::post('/traslados/{id}/confirmar', [OperacionController::class, 'finalizar'])->defaults('tipo', 'traslados')->defaults('accion', 'confirmar')->middleware('modulo:traslados');
    Route::get('/inventario/movimientos', [OperacionController::class, 'kardex'])->middleware('modulo:catalogo,ingresos,ventas,traslados,reportes');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/sucursal', [AuthController::class, 'cambiarSucursal'])->middleware('throttle:auth-refresh');
    Route::post('/auth/verificar-codigo', [AuthController::class, 'verificarCodigo'])->middleware(['modulo:ventas', 'throttle:codigo']);

    Route::get('/catalogos/sucursales', [CatalogoController::class, 'sucursales'])->middleware('modulo:catalogo,sucursales,usuarios,ventas,traslados,ingresos,reportes');
    Route::get('/catalogos/proveedores', [CatalogoController::class, 'proveedores'])->middleware('modulo:catalogo,proveedores,ingresos,reportes');
    Route::get('/catalogos/productos', [CatalogoController::class, 'productos'])->middleware('modulo:catalogo,ventas,traslados,ingresos,reportes');
    Route::get('/catalogos/destinos', [CatalogoController::class, 'destinos'])->middleware('modulo:traslados');
    Route::get('/catalogos/clientes', [CatalogoController::class, 'clientes'])->middleware('modulo:clientes,ventas,reportes');

    Route::middleware('modulo:sucursales')->group(function () {
        Route::get('/sucursales', [SucursalController::class, 'index']);
        Route::post('/sucursales', [SucursalController::class, 'store']);
        Route::get('/sucursales/buscar', [SucursalController::class, 'buscar']);
        Route::get('/sucursales/{id}', [SucursalController::class, 'show']);
        Route::put('/sucursales/{id}', [SucursalController::class, 'update']);
        Route::delete('/sucursales/{id}', [SucursalController::class, 'destroy']);
    });

    Route::middleware('modulo:proveedores')->group(function () {
        Route::get('/proveedores/buscar', [ProveedorController::class, 'buscar']);
        Route::get('/proveedores', [ProveedorController::class, 'index']);
        Route::post('/proveedores', [ProveedorController::class, 'store']);
        Route::get('/proveedores/{id}', [ProveedorController::class, 'show']);
        Route::put('/proveedores/{id}', [ProveedorController::class, 'update']);
        Route::delete('/proveedores/{id}', [ProveedorController::class, 'destroy']);
    });

    Route::middleware('modulo:clientes')->group(function () {
        Route::get('/clientes/buscar', [ClienteController::class, 'buscar']);
        Route::get('/clientes/por-nit/{nit}', [ClienteController::class, 'porNit']);
        Route::get('/clientes', [ClienteController::class, 'index']);
        Route::post('/clientes', [ClienteController::class, 'store']);
        Route::get('/clientes/{id}', [ClienteController::class, 'show']);
        Route::put('/clientes/{id}', [ClienteController::class, 'update']);
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
    });

    Route::middleware('modulo:catalogo')->group(function () {
        Route::get('/productos/buscar', [ProductoController::class, 'buscar']);
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::get('/productos/{id}', [ProductoController::class, 'show']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    });

    Route::prefix('admin/usuarios')->middleware('modulo:usuarios')->group(function () {
        Route::get('/buscar', [UsuarioController::class, 'buscar']);
        Route::get('/', [UsuarioController::class, 'index']);
        Route::post('/', [UsuarioController::class, 'store']);
        Route::get('/{id}', [UsuarioController::class, 'show']);
        Route::put('/{id}', [UsuarioController::class, 'update']);
        Route::delete('/{id}', [UsuarioController::class, 'destroy']);
    });
});
