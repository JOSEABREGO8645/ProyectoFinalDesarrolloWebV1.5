<?php

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\OperacionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! app()->environment('testing') || ! preg_match('/^libreria_auth_e2e_[a-f0-9]{8}$/', config('database.connections.mongodb.database'))) {
    throw new RuntimeException('Los reportes solo se preparan en bases temporales de pruebas.');
}
$sede = Sucursal::firstOrCreate(['nombre' => 'Reportes Central E2E'], ['activo' => true]);
$destino = Sucursal::firstOrCreate(['nombre' => 'Reportes Destino E2E'], ['activo' => true]);
$proveedor = Proveedor::firstOrCreate(['nombre' => 'Proveedor Reportes E2E'], ['activo' => true]);
$producto = Producto::firstOrCreate(['codigo' => '000-REP-E2E'], ['nombre' => 'Libro Reportes E2E', 'categoria' => 'Reportes', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '0.47', 'precio_venta' => '1.01', 'stock_minimo' => 5, 'activo' => true]);
foreach (range(1, 26) as $i) {
    Producto::firstOrCreate(['codigo' => 'REP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)], ['nombre' => 'Producto de reporte '.$i, 'categoria' => 'Reportes', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '0.10', 'precio_venta' => '0.20', 'stock_minimo' => 0, 'activo' => true]);
}
Producto::firstOrCreate(['codigo' => 'REP-HISTORICO'], ['nombre' => 'Histórico de reportes', 'categoria' => 'Reportes', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '1.00', 'precio_venta' => '2.00', 'activo' => false]);
foreach (['reportes.e2e' => ['superusuario', []], 'analista.reportes.e2e' => ['empleado', ['reportes']], 'sin.reportes.e2e' => ['empleado', ['ventas']]] as $nombre => [$rol,$modulos]) {
    Usuario::firstOrCreate(['usuario' => $nombre], ['nombre' => $nombre, 'password' => Hash::make('AutenticacionE2E2026!'), 'codigo_hash' => Hash::make('4321'), 'rol' => $rol, 'sucursal_id' => (string) $sede->getKey(), 'modulos' => $modulos, 'activo' => true]);
}
$usuario = Usuario::where('usuario', 'reportes.e2e')->firstOrFail();
$service = app(OperacionService::class);
$base = ['sucursal_id' => (string) $sede->getKey(), 'motivo' => 'Datos integrados para reportes', 'items' => [['producto_id' => (string) $producto->getKey(), 'cantidad' => 10]]];
$service->crear('ingresos', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000031', 'proveedor_id' => (string) $proveedor->getKey()]), $usuario);
$base['items'][0]['cantidad'] = 3;
$service->crear('ventas', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000032', 'codigo' => '4321', 'efectivo' => '5.00']), $usuario);
$base['items'][0]['cantidad'] = 2;
$service->crear('traslados', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000033', 'destino_id' => (string) $destino->getKey()]), $usuario);
$base['items'][0]['cantidad'] = 1;
$venta = $service->crear('ventas', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000034', 'codigo' => '4321', 'efectivo' => '2.00']), $usuario);
if ($venta->estado !== 'anulado') {
    $service->finalizar('ventas', (string) $venta->getKey(), 'anular', '=PRUEBA()', $usuario);
}
echo "Reportes de pruebas preparados.\n";
