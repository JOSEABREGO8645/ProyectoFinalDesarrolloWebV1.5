<?php

namespace Tests\Feature\Auth;

use App\Models\Sesion;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthTestCase;

class UsuariosTest extends AuthTestCase
{
    private function administrar(): Usuario
    {
        $super = Usuario::factory()->create(['rol' => 'superusuario']);
        $login = $this->login($super)->assertOk();
        $this->withToken($login->json('access_token'));

        return $super;
    }

    private function datos(string $sucursalId, array $cambios = []): array
    {
        return array_merge(['nombre' => 'Empleado ficticio', 'usuario' => 'empleado.prueba', 'password' => 'PruebaSegura2026!',
            'rol' => 'empleado', 'sucursal_id' => $sucursalId, 'modulos' => ['clientes'], 'activo' => true], $cambios);
    }

    public function test_superusuario_crea_edita_consulta_y_elimina_sin_exponer_hashes(): void
    {
        $super = $this->administrar();
        $datos = $this->datos($super->sucursal_id, ['usuario' => 'EMPLEADO.Prueba', 'codigo' => '1234']);
        $respuesta = $this->postJson('/api/admin/usuarios', $datos)->assertCreated()->assertJsonPath('datos.usuario', 'empleado.prueba')
            ->assertJsonMissingPath('datos.password')->assertJsonMissingPath('datos.codigo_hash');
        $id = $respuesta->json('datos._id');
        $usuario = Usuario::findOrFail($id);
        $this->assertTrue(Hash::check('PruebaSegura2026!', $usuario->password));
        $this->assertTrue(Hash::check('1234', $usuario->codigo_hash));
        $this->assertSame($super->sucursal_id, $usuario->sucursal_id);
        $this->getJson('/api/admin/usuarios/'.$id)->assertOk()->assertJsonPath('tiene_codigo', true)->assertJsonMissingPath('password');
        $this->getJson('/api/admin/usuarios/buscar?q=empleado.prueba&activo=1&rol=empleado')->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.password')->assertJsonMissingPath('0.codigo_hash');
        unset($datos['password'], $datos['codigo']);
        $datos['nombre'] = 'Nombre actualizado';
        $this->putJson('/api/admin/usuarios/'.$id, $datos)->assertOk();
        $this->assertSame('Nombre actualizado', $usuario->fresh()->nombre);
        $this->assertTrue(Hash::check('PruebaSegura2026!', $usuario->fresh()->password));
        $this->deleteJson('/api/admin/usuarios/'.$id)->assertOk();
        $this->assertNull(Usuario::find($id));
    }

    public function test_usuario_repetido_normalizado_devuelve_422(): void
    {
        $super = $this->administrar();
        Usuario::factory()->create(['usuario' => 'empleado.prueba']);
        $this->postJson('/api/admin/usuarios', $this->datos($super->sucursal_id, ['usuario' => 'EMPLEADO.PRUEBA']))
            ->assertUnprocessable()->assertJsonPath('message', 'Ya existe una cuenta con ese usuario.');
        $this->assertSame(2, Usuario::count());
    }

    public function test_editar_sin_estado_ni_modulos_no_reactiva_una_cuenta(): void
    {
        $super = $this->administrar();
        $empleado = Usuario::factory()->create(['activo' => false, 'modulos' => ['clientes'], 'sucursal_id' => $super->sucursal_id]);
        $this->putJson('/api/admin/usuarios/'.$empleado->getKey(), [
            'nombre' => 'Nombre actualizado', 'usuario' => $empleado->usuario,
            'rol' => 'empleado', 'sucursal_id' => $super->sucursal_id,
        ])->assertOk();
        $this->assertFalse($empleado->fresh()->activo);
        $this->assertSame(['clientes'], $empleado->fresh()->modulos);
        $this->login($empleado)->assertUnauthorized();
    }

    #[DataProvider('entradasInvalidas')]
    public function test_entrada_invalida_no_crea_cuentas(array $cambios, string $campo): void
    {
        $super = $this->administrar();
        $this->postJson('/api/admin/usuarios', $this->datos($super->sucursal_id, $cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertSame(1, Usuario::count());
    }

    public static function entradasInvalidas(): array
    {
        return [
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'sin contraseña' => [['password' => ''], 'password'],
            'contraseña corta' => [['password' => 'corta'], 'password'],
            'contraseña multibyte larga' => [['password' => str_repeat('ñ', 40)], 'password'],
            'usuario inválido' => [['usuario' => 'usuario con espacios'], 'usuario'],
            'rol inválido' => [['rol' => 'administrador'], 'rol'],
            'módulo inexistente' => [['modulos' => ['inexistente']], 'modulos.0'],
            'módulo administrativo' => [['modulos' => ['usuarios']], 'modulos.0'],
            'módulo duplicado' => [['modulos' => ['clientes', 'clientes']], 'modulos.0'],
            'sucursal inválida' => [['sucursal_id' => 'invalido'], 'sucursal_id'],
            'código inválido' => [['codigo' => 'abc'], 'codigo'],
        ];
    }

    public function test_sucursal_inexistente_o_inactiva_devuelve_422(): void
    {
        $super = $this->administrar();
        $this->postJson('/api/admin/usuarios', $this->datos('000000000000000000000000'))->assertUnprocessable()->assertJsonPath('message', 'La sucursal seleccionada no existe.');
        $inactiva = Sucursal::create(['nombre' => 'Inactiva', 'activo' => false]);
        $this->postJson('/api/admin/usuarios', $this->datos((string) $inactiva->getKey()))->assertUnprocessable();
        $this->assertSame(1, Usuario::count());
    }

    public function test_superusuario_no_puede_desactivarse_degradarse_ni_eliminarse(): void
    {
        $super = $this->administrar();
        $datos = $this->datos($super->sucursal_id, ['usuario' => $super->usuario, 'rol' => 'superusuario', 'password' => null]);
        $this->putJson('/api/admin/usuarios/'.$super->getKey(), array_merge($datos, ['activo' => false]))->assertUnprocessable();
        $this->putJson('/api/admin/usuarios/'.$super->getKey(), array_merge($datos, ['rol' => 'empleado']))->assertUnprocessable();
        $this->deleteJson('/api/admin/usuarios/'.$super->getKey())->assertConflict();
        $this->assertSame('superusuario', $super->fresh()->rol);
        $this->assertTrue($super->fresh()->activo);
        $this->getJson('/api/auth/me')->assertOk();
    }

    #[DataProvider('cambiosSeguridad')]
    public function test_cambiar_seguridad_revoca_access_y_refresh_del_usuario(array $cambios): void
    {
        $super = $this->administrar();
        $empleado = Usuario::factory()->create(['modulos' => ['clientes'], 'sucursal_id' => $super->sucursal_id]);
        $login = $this->login($empleado)->assertOk();
        $datos = $this->datos($super->sucursal_id, array_merge(['usuario' => $empleado->usuario, 'password' => null], $cambios));
        $this->putJson('/api/admin/usuarios/'.$empleado->getKey(), $datos)->assertOk();
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($login))->assertUnauthorized();
        $this->assertNotNull(Sesion::where('usuario_id', (string) $empleado->getKey())->firstOrFail()->revoked_at);
    }

    public static function cambiosSeguridad(): array
    {
        return [
            'desactivar' => [['activo' => false]],
            'cambiar contraseña' => [['password' => 'NuevaPrueba2026!']],
            'cambiar módulos' => [['modulos' => ['catalogo']]],
            'cambiar rol' => [['rol' => 'superusuario']],
        ];
    }

    public function test_desactivar_sucursal_corta_acceso_y_refresh(): void
    {
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        Sucursal::findOrFail($usuario->sucursal_id)->update(['activo' => false]);
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($login))->assertUnauthorized();
    }

    public function test_version_de_seguridad_rechaza_sesiones_anteriores_aunque_no_se_limpien(): void
    {
        $super = $this->administrar();
        $empleado = Usuario::factory()->create(['sucursal_id' => $super->sucursal_id, 'modulos' => ['clientes']]);
        $login = $this->login($empleado)->assertOk();
        $this->putJson('/api/admin/usuarios/'.$empleado->getKey(), $this->datos($super->sucursal_id, ['usuario' => $empleado->usuario, 'password' => 'NuevaSegura2026!']))->assertOk();
        Sesion::where('usuario_id', (string) $empleado->getKey())->update(['revoked_at' => null]);
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($login))->assertUnauthorized();
    }

    public function test_eliminar_usuario_revoca_sus_sesiones(): void
    {
        $this->administrar();
        $empleado = Usuario::factory()->create();
        $login = $this->login($empleado)->assertOk();
        $this->deleteJson('/api/admin/usuarios/'.$empleado->getKey())->assertOk();
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($login))->assertUnauthorized();
        $this->assertNull($empleado->fresh());
    }

    public function test_usuario_con_historial_no_se_elimina(): void
    {
        $this->administrar();
        $empleado = Usuario::factory()->create();
        DB::connection('mongodb')->table('ventas')->insert(['usuario_id' => (string) $empleado->getKey()]);
        $this->deleteJson('/api/admin/usuarios/'.$empleado->getKey())->assertConflict();
        $this->assertNotNull($empleado->fresh());
    }

    public function test_sucursal_con_usuarios_activos_no_se_puede_desactivar(): void
    {
        $super = $this->administrar();
        $this->putJson('/api/sucursales/'.$super->sucursal_id, ['nombre' => 'Sucursal de pruebas', 'activo' => false])
            ->assertConflict()->assertJsonPath('message', 'Reasigne o desactive los usuarios de esta sucursal antes de desactivarla.');
        $this->assertTrue(Sucursal::findOrFail($super->sucursal_id)->activo);
        $this->getJson('/api/auth/me')->assertOk();
    }

    #[DataProvider('modulosActuales')]
    public function test_empleado_asignado_puede_consultar_su_modulo(string $modulo, string $ruta): void
    {
        $empleado = Usuario::factory()->create(['modulos' => [$modulo]]);
        $login = $this->login($empleado)->assertOk();
        $this->withToken($login->json('access_token'))->getJson($ruta)->assertOk();
        $this->getJson('/api/admin/usuarios')->assertForbidden();
    }

    public static function modulosActuales(): array
    {
        return [['sucursales', '/api/sucursales'], ['catalogo', '/api/productos'], ['clientes', '/api/clientes'], ['proveedores', '/api/proveedores']];
    }

    public function test_usuario_inexistente_devuelve_404_y_registro_publico_no_existe(): void
    {
        $this->administrar();
        $this->getJson('/api/admin/usuarios/000000000000000000000000')->assertNotFound();
        $this->getJson('/api/admin/usuarios/invalido')->assertNotFound();
        $this->postJson('/api/register', [])->assertNotFound();
        $this->postJson('/api/auth/register', [])->assertNotFound();
    }

    public function test_comando_inicial_crea_hash_y_archivo_privado_sin_reemplazar_cuentas(): void
    {
        Storage::fake('local');
        Sucursal::create(['nombre' => 'Sucursal inicial', 'activo' => true]);
        $this->artisan('libreria:crear-admin', ['--generar-clave' => true, '--no-interaction' => true])->assertSuccessful();
        $super = Usuario::where('usuario', 'admin')->firstOrFail();
        $this->assertSame('superusuario', $super->rol);
        Storage::disk('local')->assertExists('acceso-inicial.txt');
        $texto = Storage::disk('local')->get('acceso-inicial.txt');
        preg_match('/Contraseña: (.+)/u', $texto, $partes);
        $this->assertTrue(Hash::check($partes[1], $super->password));
        config(['jwt.bootstrap_password' => 'OtraPruebaSegura2026!']);
        $this->artisan('libreria:crear-admin', ['--usuario' => 'otro.admin', '--no-interaction' => true])->assertFailed();
        $this->assertSame(1, Usuario::count());
    }

    public function test_instalacion_vacia_puede_crear_sucursal_inicial_con_opcion_explicita(): void
    {
        Storage::fake('local');
        $this->artisan('libreria:crear-admin', ['--crear-sucursal' => true, '--generar-clave' => true, '--no-interaction' => true])->assertSuccessful();
        $admin = Usuario::where('usuario', 'admin')->firstOrFail();
        $this->assertSame('Sucursal Central', Sucursal::findOrFail($admin->sucursal_id)->nombre);
        $this->assertSame(1, Sucursal::count());
    }
}
