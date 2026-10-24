<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$base = config('database.connections.mongodb.database');
if (! app()->environment('testing') || ! preg_match('/^libreria_auth_e2e_[a-f0-9]{8}$/', $base)) {
    throw new RuntimeException('Solo se permite limpiar la caché temporal de las pruebas.');
}
$resultados = realpath(base_path('../frontend/test-results'));
$esperado = $resultados.DIRECTORY_SEPARATOR.$base.DIRECTORY_SEPARATOR.'cache';
if (! $resultados || ! is_dir($esperado) || realpath(config('cache.stores.file.path')) !== realpath($esperado)) {
    throw new RuntimeException('La caché configurada no coincide con la carpeta temporal autorizada.');
}
if (! Cache::store('file')->flush()) {
    throw new RuntimeException('No se pudo limpiar la caché temporal.');
}
