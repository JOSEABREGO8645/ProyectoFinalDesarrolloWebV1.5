<?php

namespace App\Console\Commands;

use App\Exceptions\ReglaNegocioException;
use App\Http\Requests\UsuarioRequest;
use App\Repositories\SucursalRepository;
use App\Services\SucursalService;
use App\Services\UsuarioService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

#[Signature('libreria:crear-admin {--usuario=admin} {--nombre=Administrador} {--sucursal=} {--crear-sucursal} {--generar-clave}')]
#[Description('Crear el primer superusuario sin registro público ni contraseñas en el código')]
class CrearAdministrador extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(UsuarioService $usuarios, SucursalRepository $sucursales, SucursalService $catalogoSucursales): int
    {
        $sucursalId = $this->option('sucursal') ?: $sucursales->obtenerTodos()->first(fn ($sucursal) => $sucursal->activo)?->getKey();
        if (! $sucursalId && $this->option('crear-sucursal')) {
            $sucursalId = $catalogoSucursales->agregar(['nombre' => 'Sucursal Central', 'activo' => true])->getKey();
        }
        if (! $sucursalId) {
            $this->error('Primero debe registrar una sucursal activa.');

            return self::FAILURE;
        }
        if ($this->option('generar-clave') && ! app()->environment(['local', 'testing'])) {
            $this->error('La generación de un archivo de acceso inicial solo está disponible en local/testing.');

            return self::FAILURE;
        }
        $password = $this->option('generar-clave') ? bin2hex(random_bytes(12)) : config('jwt.bootstrap_password');
        if (! $password && ! $this->input->isInteractive()) {
            $this->error('Configure LIBRERIA_ADMIN_PASSWORD o utilice --generar-clave en local.');

            return self::FAILURE;
        }
        if (! $password) {
            $password = $this->secret('Contraseña inicial (mínimo 10 caracteres)');
        }
        $datos = ['nombre' => $this->option('nombre'), 'usuario' => $this->option('usuario'),
            'password' => $password, 'sucursal_id' => (string) $sucursalId, 'rol' => 'superusuario'];
        $request = UsuarioRequest::create('/api/admin/usuarios', 'POST');
        $validador = Validator::make($datos, $request->rules());
        if ($validador->fails()) {
            $this->error($validador->errors()->first());

            return self::FAILURE;
        }
        if ($this->option('generar-clave') && Storage::disk('local')->exists('acceso-inicial.txt')) {
            $this->error('Ya existe storage/app/private/acceso-inicial.txt. No se sobrescribirá.');

            return self::FAILURE;
        }
        if ($this->option('generar-clave') && ! Storage::disk('local')->put('acceso-inicial.txt', 'Usuario: '.mb_strtolower(trim($datos['usuario']))."\nContraseña: {$password}\nCambie la contraseña desde Usuarios después de ingresar.\n")) {
            $this->error('No se pudo guardar el acceso inicial; no se creó ninguna cuenta.');

            return self::FAILURE;
        }
        try {
            $usuario = $usuarios->crearInicial($datos);
        } catch (ReglaNegocioException $e) {
            if ($this->option('generar-clave')) {
                Storage::disk('local')->delete('acceso-inicial.txt');
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($this->option('generar-clave')) {
            $this->info('Acceso inicial guardado en storage/app/private/acceso-inicial.txt (excluido de Git).');
        }
        $this->info('Superusuario creado: '.$usuario->usuario);

        return self::SUCCESS;
    }
}
