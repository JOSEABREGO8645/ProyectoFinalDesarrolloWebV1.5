<?php

use App\Exceptions\ReglaNegocioException;
use App\Repositories\UsuarioRepository;
use App\Services\OperacionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$datos = json_decode(base64_decode($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (! app()->environment('testing') || ! preg_match('/^libreria_catalogos_test_[a-f0-9]{16}$/', $datos['base'])) {
    throw new RuntimeException('Solo se permite concurrencia contra bases temporales de pruebas.');
}
config(['database.connections.mongodb.database' => $datos['base']]);
DB::purge('mongodb');
$usuario = app(UsuarioRepository::class)->buscarPorId($datos['usuario_id']);
if (isset($datos['sucursal_activa'])) {
    $usuario->setAttribute('sucursal_id', $datos['sucursal_activa']);
}
while (microtime(true) < $datos['inicio']) {
    usleep(10000);
}
try {
    $service = app(OperacionService::class);
    if ($datos['accion'] === 'crear') {
        $service->crear($datos['tipo'], $datos['datos'], $usuario);
        $estado = 201;
    } else {
        $service->finalizar($datos['tipo'], $datos['id'], $datos['accion'], 'Operación concurrente de prueba', $usuario);
        $estado = 200;
    }
} catch (ReglaNegocioException $e) {
    $estado = $e->render()->getStatusCode();
}
echo json_encode(['estado' => $estado]), PHP_EOL;
