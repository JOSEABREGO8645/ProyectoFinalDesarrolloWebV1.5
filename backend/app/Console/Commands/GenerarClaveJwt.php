<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('libreria:jwt-clave')]
#[Description('Configurar una clave JWT aleatoria si aún no existe; no reemplaza claves configuradas')]
class GenerarClaveJwt extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (is_string(config('jwt.secret')) && strlen(config('jwt.secret')) >= 32) {
            $this->info('JWT_SECRET ya está configurada; se conserva la clave existente.');

            return self::SUCCESS;
        }
        $ruta = app()->environmentFilePath();
        if (! is_file($ruta)) {
            $this->error('Primero copie .env.example a .env.');

            return self::FAILURE;
        }
        $lineas = preg_split('/\R/', file_get_contents($ruta));
        $lineas = array_values(array_filter($lineas, fn (string $linea) => ! str_starts_with(trim($linea), 'JWT_SECRET=')));
        $lineas[] = 'JWT_SECRET='.bin2hex(random_bytes(32));
        if (file_put_contents($ruta, implode(PHP_EOL, $lineas).PHP_EOL, LOCK_EX) === false) {
            $this->error('No se pudo guardar la clave JWT en .env.');

            return self::FAILURE;
        }
        $this->callSilent('config:clear');
        $this->info('Clave JWT configurada en .env sin mostrarla en pantalla.');

        return self::SUCCESS;
    }
}
