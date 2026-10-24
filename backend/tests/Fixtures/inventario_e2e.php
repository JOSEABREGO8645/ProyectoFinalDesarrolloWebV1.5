<?php

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! app()->environment('testing') || ! preg_match('/^libreria_auth_e2e_[a-f0-9]{8}$/', config('database.connections.mongodb.database'))) {
    throw new RuntimeException('Solo se permite preparar inventario en bases temporales de pruebas.');
}
if ($kernel->call('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php', '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('No se pudieron preparar los índices del inventario.');
}
$sucursal = Sucursal::where('nombre', 'Sucursal E2E')->firstOrFail();
$destino = Sucursal::firstOrCreate(['nombre' => 'Destino Operaciones E2E'], ['activo' => true]);
$proveedor = Proveedor::firstOrCreate(['nombre' => 'Proveedor Operaciones E2E'], ['activo' => true]);
Producto::firstOrCreate(['codigo' => '000000-OPS-E2E'], ['nombre' => 'Producto Operaciones E2E', 'proveedor_id' => (string) $proveedor->getKey(),
    'precio_costo' => '0.47', 'precio_venta' => '1.01', 'categoria' => 'Pruebas', 'stock_minimo' => 1, 'activo' => true]);
foreach (['operaciones.e2e' => ['superusuario', $sucursal, []], 'inventario.postman.e2e' => ['superusuario', $sucursal, []],
    'origen.operaciones.e2e' => ['empleado', $sucursal, ['ventas', 'ingresos', 'traslados']],
    'destino.operaciones.e2e' => ['empleado', $destino, ['traslados']]] as $nombre => [$rol, $sede, $modulos]) {
    Usuario::firstOrCreate(['usuario' => $nombre], ['nombre' => $nombre, 'password' => Hash::make('AutenticacionE2E2026!'),
        'codigo_hash' => Hash::make('4321'), 'rol' => $rol, 'sucursal_id' => (string) $sede->getKey(), 'modulos' => $modulos, 'activo' => true]);
}
Usuario::where('usuario', 'postman.e2e')->update(['codigo_hash' => Hash::make('4321')]);
echo "Inventario de pruebas preparado.\n";
