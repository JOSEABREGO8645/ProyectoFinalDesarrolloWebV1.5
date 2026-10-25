<?php

namespace App\Console\Commands;

use App\Exceptions\ReglaNegocioException;
use App\Services\DemoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('libreria:demo {--base=libreria_demo}')]
#[Description('Preparar datos ficticios persistentes en una base aislada sin reemplazar información')]
class PrepararDemostracion extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DemoService $service): int
    {
        $base = $this->option('base');
        $original = config('database.connections.mongodb.database');
        if (! app()->environment(['local', 'testing']) || ! is_string($base) || ! preg_match('/^libreria_demo(?:_test_[a-f0-9]{16})?$/D', $base) || $base === $original) {
            $this->error('Solo se permite libreria_demo en local/testing, separada de la base configurada.');

            return self::FAILURE;
        }
        $ruta = storage_path('app/private/demo/'.$base.'/accesos.json');
        try {
            $accesos = $this->accesos($ruta, $base);
            config(['database.connections.mongodb.database' => $base]);
            DB::purge('mongodb');
            $service->validar($base, $accesos);
            $estado = $this->callSilent('migrate', ['--database' => 'mongodb', '--path' => ['database/migrations/2026_10_23_232450_crear_indices_inventario.php', 'database/migrations/2026_10_24_054617_agregar_indices_dashboard.php'], '--no-interaction' => true]);
            if ($estado !== 0) {
                $this->error('No se pudieron preparar los índices de demostración.');

                return self::FAILURE;
            }
            $resultado = $service->preparar($base, $accesos);
            $this->info($resultado['nueva'] ? 'Demostración preparada: '.$base : 'La demostración ya estaba preparada; se conservan sus cambios.');
            $this->table(['Contenido', 'Cantidad'], array_map(fn ($nombre, $cantidad) => [$nombre, $cantidad], array_keys($resultado['resumen']), $resultado['resumen']));
            $this->info('Accesos privados: '.$ruta);

            return self::SUCCESS;
        } catch (ReglaNegocioException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            DB::purge('mongodb');
            config(['database.connections.mongodb.database' => $original]);
        }
    }

    private function accesos(string $ruta, string $base): array
    {
        if (is_file($ruta)) {
            $datos = json_decode(file_get_contents($ruta), true);
            if (! is_array($datos) || ($datos['version'] ?? null) !== 1 || ($datos['base'] ?? null) !== $base
                || ! is_string($datos['jwt_secret'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $datos['jwt_secret'])
                || ! is_array($datos['cuentas'] ?? null) || ! array_is_list($datos['cuentas']) || count($datos['cuentas']) !== 5) {
                throw new ReglaNegocioException('El archivo privado de accesos no es válido. No se reemplazará.');
            }
            foreach ($datos['cuentas'] as $i => $cuenta) {
                if (! is_array($cuenta) || ($cuenta['usuario'] ?? null) !== ['admin.demo', 'caja.demo', 'bodega.demo', 'reportes.demo', 'destino.demo'][$i]
                    || ! is_string($cuenta['password'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $cuenta['password'])
                    || ! is_string($cuenta['codigo'] ?? null) || ! preg_match('/^[0-9]{4}$/D', $cuenta['codigo'])) {
                    throw new ReglaNegocioException('El archivo privado de accesos no es válido. No se reemplazará.');
                }
            }

            return $datos;
        }
        if (! is_dir(dirname($ruta)) && ! mkdir(dirname($ruta), 0700, true)) {
            throw new ReglaNegocioException('No se pudo preparar la carpeta privada.');
        }
        $cuentas = [];
        foreach (['admin.demo', 'caja.demo', 'bodega.demo', 'reportes.demo', 'destino.demo'] as $usuario) {
            $cuentas[] = ['usuario' => $usuario, 'password' => bin2hex(random_bytes(16)), 'codigo' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)];
        }
        $datos = ['version' => 1, 'base' => $base, 'jwt_secret' => bin2hex(random_bytes(32)), 'cuentas' => $cuentas];
        $contenido = json_encode($datos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $archivo = fopen($ruta, 'x');
        if (! $archivo) {
            throw new ReglaNegocioException('El archivo ya existe o no se pudo crear. Reintente sin reemplazarlo.');
        }
        try {
            $escrito = fwrite($archivo, $contenido);
        } finally {
            fclose($archivo);
        }
        chmod($ruta, 0600);
        if ($escrito !== strlen($contenido)) {
            throw new ReglaNegocioException('No se pudo guardar el archivo completo de accesos.');
        }

        return $datos;
    }
}
