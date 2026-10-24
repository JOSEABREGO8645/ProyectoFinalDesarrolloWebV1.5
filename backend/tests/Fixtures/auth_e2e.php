<?php

use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$base = config('database.connections.mongodb.database');
if (! app()->environment('testing') || ! preg_match('/^libreria_auth_e2e_[a-f0-9]{8}$/', $base)) {
    throw new RuntimeException('Los fixtures de navegador solo pueden ejecutarse en una base temporal de pruebas.');
}
$password = 'AutenticacionE2E2026!';
$sucursal = Sucursal::firstOrCreate(['nombre' => 'Sucursal E2E'], ['activo' => true]);
foreach (['admin.e2e', 'postman.e2e', 'catalogos.e2e'] as $nombre) {
    Usuario::firstOrCreate(['usuario' => $nombre], [
        'nombre' => 'Administrador E2E', 'password' => Hash::make($password),
        'rol' => 'superusuario', 'sucursal_id' => (string) $sucursal->getKey(), 'modulos' => [], 'activo' => true,
    ]);
}
echo json_encode(['base' => $base, 'sucursal_id' => (string) $sucursal->getKey()]), PHP_EOL;
