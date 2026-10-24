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
    throw new RuntimeException('El dashboard solo se prepara en bases temporales de pruebas.');
}
$kernel->call('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_24_054617_agregar_indices_dashboard.php', '--no-interaction' => true]);
$sede = Sucursal::firstOrCreate(['nombre' => 'Dashboard Central E2E'], ['activo' => true]);
$destino = Sucursal::firstOrCreate(['nombre' => 'Dashboard Destino E2E'], ['activo' => true]);
$proveedor = Proveedor::firstOrCreate(['nombre' => 'Dashboard Proveedor E2E'], ['activo' => true]);
$producto = Producto::firstOrCreate(['codigo' => '000-DASH-E2E'], ['nombre' => 'Producto Dashboard E2E', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '0.47', 'precio_venta' => '1.01', 'stock_minimo' => 5, 'activo' => true]);
Producto::firstOrCreate(['codigo' => '000-DASH-AGOTADO'], ['nombre' => 'Agotado Dashboard E2E', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '1.00', 'precio_venta' => '2.00', 'stock_minimo' => 1, 'activo' => true]);
foreach (['dashboard.e2e' => ['superusuario', []], 'dashboard.caja.e2e' => ['empleado', ['ventas']], 'dashboard.sin.e2e' => ['empleado', ['clientes']]] as $nombre => [$rol,$modulos]) {
    Usuario::firstOrCreate(['usuario' => $nombre], ['nombre' => $nombre, 'password' => Hash::make('AutenticacionE2E2026!'), 'codigo_hash' => Hash::make('4321'), 'rol' => $rol, 'modulos' => $modulos, 'sucursal_id' => (string) $sede->getKey(), 'activo' => true]);
}
$usuario = Usuario::where('usuario', 'dashboard.e2e')->firstOrFail();
$service = app(OperacionService::class);
$base = ['sucursal_id' => (string) $sede->getKey(), 'motivo' => 'Dashboard E2E', 'items' => [['producto_id' => (string) $producto->getKey(), 'cantidad' => 10]]];
$service->crear('ingresos', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000011', 'proveedor_id' => (string) $proveedor->getKey()]), $usuario);
$base['items'][0]['cantidad'] = 3;
$service->crear('ventas', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000012', 'codigo' => '4321', 'efectivo' => '5.00']), $usuario);
$base['items'][0]['cantidad'] = 2;
$service->crear('traslados', array_merge($base, ['operacion_id' => '00000000-0000-4000-8000-000000000013', 'destino_id' => (string) $destino->getKey()]), $usuario);
echo "Dashboard de pruebas preparado.\n";
