<?php

/** Nombres y contactos ficticios para la instancia publicada; sin alterar saldos. */
require dirname(__DIR__, 2).'/backend/vendor/autoload.php';

use App\Repositories\PublicacionDatosRepository;
use App\Services\PublicacionDatosService;
use MongoDB\Client;

$raiz = dirname(__DIR__, 2);
$privado = $raiz.'/backend/storage/app/private/publicacion';
$config = json_decode(file_get_contents($privado.'/config.json'), true, flags: JSON_THROW_ON_ERROR);
if (! str_ends_with(parse_url($config['DB_URI'], PHP_URL_HOST) ?? '', '.mongodb.net')) {
    throw new RuntimeException('La personalización requiere el destino Atlas de publicación.');
}
$db = (new Client($config['DB_URI']))->selectDatabase('libreria_demo');
$repository = new PublicacionDatosRepository($db, ['erp' => $db]);
$mapa = [];
$sucursales = ['Cuilapa Centro', 'Barberena', 'Chiquimulilla', 'Casillas', 'San Rafael Las Flores'];
$direcciones = ['4a avenida 2-15, zona 1, Cuilapa', '2a calle 3-40, zona 1, Barberena', '3a avenida 1-28, zona 1, Chiquimulilla', '1a calle 2-10, Casillas', 'Avenida Central 4-22, San Rafael Las Flores'];
$proveedores = ['Distribuidora El Estudiante', 'Papelería Horizonte', 'Suministros Escolares del Sur', 'Editorial Letras Abiertas', 'Comercial Papel y Color'];
$clientes = ['Mariana Isabel Pérez López', 'Diego Alejandro Hernández Cruz', 'Lucía Fernanda Morales Ruiz', 'Andrés Felipe García Méndez', 'Valeria Sofía Castillo Reyes'];
foreach (['Central', 'Norte', 'Sur', 'Oriente', 'Occidente'] as $i => $viejo) {
    $mapa['DEMO - '.$viejo] = $sucursales[$i];
    $mapa['Proveedor ficticio '.($i + 1)] = $proveedores[$i];
    $mapa['Cliente ficticio '.($i + 1)] = $clientes[$i];
    $mapa['Dirección ficticia '.($i + 1)] = $direcciones[$i];
    $mapa['9900000'.($i + 1)] = '5551210'.($i + 1);
    $mapa['9900010'.($i + 1)] = '5551320'.($i + 1);
    $mapa['9900020'.($i + 1)] = '5551430'.($i + 1);
    $mapa['proveedor'.($i + 1).'@example.com'] = 'pedidos'.($i + 1).'@papeleria.example';
    $mapa['cliente'.($i + 1).'@example.com'] = 'contacto'.($i + 1).'@correo.example';
    $baseNit = (string) (10000001 + $i);
    $suma = 0;
    foreach (str_split($baseNit) as $j => $digito) {
        $suma += (int) $digito * (strlen($baseNit) + 1 - $j);
    }
    $verificador = (11 - $suma % 11) % 11;
    $mapa['DEMO000'.($i + 1)] = $baseNit.'-'.($verificador === 10 ? 'K' : $verificador);
}
$mapa['Dirección ficticia'] = '2a avenida 5-18, zona 1, Santa Rosa';
$nombres = ['Cuaderno de líneas', 'Lápiz de grafito', 'Carpeta escolar', 'Marcador permanente', 'Libro de lectura', 'Borrador blanco', 'Regla escolar', 'Papel de colores', 'Pegamento escolar', 'Tijera escolar'];
$detalles = ['Cuaderno de líneas 80 hojas tamaño carta', 'Lápiz de grafito HB con borrador', 'Carpeta escolar tamaño oficio con gancho', 'Marcador permanente negro punta fina', 'Libro de lectura Aventuras del Bosque', 'Borrador blanco de goma suave', 'Regla transparente de 30 centímetros', 'Papel de colores tamaño carta paquete de 20 hojas', 'Pegamento blanco escolar de 120 ml', 'Tijera escolar de punta redonda'];
foreach ($nombres as $i => $nombre) {
    $mapa['DEMO - '.$nombre] = $detalles[$i];
    $base = '20000000'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
    $suma = 0;
    foreach (str_split($base) as $j => $digito) {
        $suma += (int) $digito * ($j % 2 === 0 ? 1 : 3);
    }
    $mapa['000-DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)] = $base.((10 - $suma % 10) % 10);
}
$usuarios = ['admin.demo' => ['administracion', 'Gabriela Alejandra Torres'], 'caja.demo' => ['maria.lopez', 'María José López García'], 'bodega.demo' => ['carlos.mendez', 'Carlos Eduardo Méndez'], 'reportes.demo' => ['ana.morales', 'Ana Lucía Morales'], 'destino.demo' => ['luis.ramirez', 'Luis Fernando Ramírez']];
foreach ($usuarios as $viejo => [$usuario, $nombre]) {
    $mapa[$viejo] = $usuario;
    $mapa['DEMO - '.$viejo] = $nombre;
}
$mapa += ['Datos ficticios para demostración' => 'Abastecimiento de temporada escolar', 'Traslado ficticio de demostración' => 'Reposición de útiles escolares entre sucursales', 'Demostración del historial' => 'Ajuste de distribución entre sucursales', 'Documento ficticio para anular' => 'Registro con detalle pendiente de corregir', 'Anulación ficticia de demostración' => 'Corrección de cantidades ingresadas'];
$service = new PublicacionDatosService($repository);
$respaldo = in_array('--aplicar', $argv, true) ? $privado.'/backups/etiquetas-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)) : null;
echo json_encode($service->personalizarEtiquetas($mapa, $respaldo), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
if ($respaldo !== null) {
    $ruta = $privado.'/storage/app/private/demo/libreria_demo/accesos.json';
    $accesos = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    foreach ($accesos['cuentas'] as &$cuenta) {
        $cuenta['usuario'] = $usuarios[$cuenta['usuario']][0] ?? $cuenta['usuario'];
    }
    if (file_put_contents($ruta, json_encode($accesos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('No se pudieron actualizar los accesos privados.');
    }
    $ruta = $privado.'/accesos-docente.txt';
    $texto = file_get_contents($ruta);
    $texto = strtr($texto, $mapa);
    if (file_put_contents($ruta, $texto) === false) {
        throw new RuntimeException('No se pudieron actualizar los accesos del docente.');
    }
    echo "Personalización confirmada; accesos privados actualizados.\n";
}
