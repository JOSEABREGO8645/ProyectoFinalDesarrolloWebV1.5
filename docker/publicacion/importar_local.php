<?php

/** Copia administrativa: conserva BSON, IDs, documentos y kardex existentes. */
require dirname(__DIR__, 2).'/backend/vendor/autoload.php';

use App\Repositories\PublicacionDatosRepository;
use App\Services\PublicacionDatosService;
use MongoDB\BSON\Document;
use MongoDB\Client;

$raiz = dirname(__DIR__, 2);
$privado = $raiz.'/backend/storage/app/private/publicacion';
$env = Dotenv\Dotenv::parse(file_get_contents($raiz.'/backend/.env'));
$config = json_decode(file_get_contents($privado.'/config.json'), true, flags: JSON_THROW_ON_ERROR);
$administracion = json_decode(file_get_contents($privado.'/conexion-preparacion-atlas.json'), true, flags: JSON_THROW_ON_ERROR);
if ($env['DB_DATABASE'] !== 'libreria_san_francisco'
    || ! in_array(parse_url($env['DB_URI'], PHP_URL_HOST), ['localhost', '127.0.0.1'], true)
    || ! str_ends_with(parse_url($administracion['DB_URI'], PHP_URL_HOST) ?? '', '.mongodb.net')
    || parse_url($administracion['DB_URI'], PHP_URL_HOST) !== parse_url($config['DB_URI'], PHP_URL_HOST)
    || parse_url($administracion['DB_URI'], PHP_URL_HOST) !== parse_url($config['API_FACTURACION_MONGO_URI'], PHP_URL_HOST)
    || parse_url($administracion['DB_URI'], PHP_URL_HOST) !== parse_url($config['API_PAGOS_MONGO_URI'], PHP_URL_HOST)) {
    throw new RuntimeException('Se requiere el origen local habitual y el destino Atlas autorizado.');
}
$local = (new Client($env['DB_URI']))->selectDatabase($env['DB_DATABASE']);
$clienteAtlas = new Client($administracion['DB_URI']);
$destinos = ['erp' => $clienteAtlas->selectDatabase('libreria_demo')];
$adicionales = [];
foreach (['facturacion' => 'ventas', 'pagos' => 'ingresos'] as $tipo => $coleccion) {
    $ids = PublicacionDatosRepository::idsDocumentos($local, $coleccion);
    $archivo = $raiz.'/backend/storage/app/private/apis-demo/'.$tipo.'.json';
    $datos = is_file($archivo) ? json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR) : ['registros' => []];
    $documentos = [];
    foreach ($datos['registros'] as [$clave, $registro]) {
        if (isset($ids[$registro['respuesta']['documento_id']])) {
            $documentos[] = Document::fromPHP(['_id' => $clave, 'huella' => $registro['huella'], 'respuesta' => $registro['respuesta']]);
        }
    }
    $destinos[$tipo] = $clienteAtlas->selectDatabase('libreria_'.$tipo.'_demo');
    $adicionales[$tipo] = ['comprobantes' => $documentos];
}
$service = new PublicacionDatosService(new PublicacionDatosRepository($local, $destinos, $adicionales));
$plan = $service->preparar();
echo json_encode($plan['resumen'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
if (in_array('--aplicar', $argv, true)) {
    $respaldo = $privado.'/backups/importacion-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
    $service->aplicar($plan, $respaldo);
    echo 'Importacion confirmada. Respaldo privado: '.str_replace($raiz.'/', '', $respaldo).PHP_EOL;
} else {
    echo 'Solo comprobacion; no se modificaron datos.'.PHP_EOL;
}
