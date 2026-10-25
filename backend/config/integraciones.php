<?php

$ruta = storage_path('app/private/apis-demo/config.json');
$local = in_array(env('APP_ENV'), ['local', 'testing'], true) && is_file($ruta) ? (json_decode(file_get_contents($ruta), true) ?? []) : [];

return [
    'banguat_ca' => env('BANGUAT_CA_BUNDLE') ?: (in_array(env('APP_ENV'), ['local', 'testing'], true) && is_file(storage_path('app/private/apis-demo/ca-windows.pem')) ? storage_path('app/private/apis-demo/ca-windows.pem') : null),
    'facturacion' => ['url' => env('FACTURACION_API_URL') ?: ($local['facturacion']['url'] ?? ''), 'token' => env('FACTURACION_API_TOKEN') ?: ($local['facturacion']['token'] ?? '')],
    'pagos' => ['url' => env('PAGOS_API_URL') ?: ($local['pagos']['url'] ?? ''), 'token' => env('PAGOS_API_TOKEN') ?: ($local['pagos']['token'] ?? '')],
    'banguat_url' => env('BANGUAT_API_URL', 'https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx'),
    'conexion_segundos' => 2, 'respuesta_segundos' => 8, 'lease_segundos' => 30, 'cambio_cache_segundos' => 1800,
];
