<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepararApisDemo extends Command
{
    protected $signature = 'libreria:apis-demo';

    protected $description = 'Prepara credenciales privadas de los dos servicios HTTP educativos, sin reemplazarlas';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo prepara servicios locales de prueba.');

            return self::FAILURE;
        }
        $ruta = storage_path('app/private/apis-demo/config.json');
        if (! is_file($ruta)) {
            if (! is_dir(dirname($ruta)) && ! mkdir(dirname($ruta), 0700, true)) {
                $this->error('No se pudo crear la carpeta privada.');

                return self::FAILURE;
            }
            $datos = ['version' => 1, 'facturacion' => ['url' => 'http://127.0.0.1:8101', 'puerto' => 8101, 'token' => bin2hex(random_bytes(32))],
                'pagos' => ['url' => 'http://127.0.0.1:8102', 'puerto' => 8102, 'token' => bin2hex(random_bytes(32))]];
            $archivo = fopen($ruta, 'x');
            if (! $archivo) {
                $this->error('La configuración ya se creó en otro proceso.');

                return self::FAILURE;
            }
            fwrite($archivo, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            fclose($archivo);
            chmod($ruta, 0600);
        } else {
            $datos = json_decode(file_get_contents($ruta), true);
            foreach (['facturacion', 'pagos'] as $tipo) {
                if (! is_array($datos) || ! preg_match('/^[a-f0-9]{64}$/', $datos[$tipo]['token'] ?? '')) {
                    $this->error('La configuración privada existente no es válida; se conserva.');

                    return self::FAILURE;
                }
            }
        }
        $this->callSilent('config:clear');
        $this->info('Configuración privada preparada; no se muestran ni reemplazan las claves.');
        $this->info('Desde la raíz: node docker/apis/iniciar.mjs');

        return self::SUCCESS;
    }
}
