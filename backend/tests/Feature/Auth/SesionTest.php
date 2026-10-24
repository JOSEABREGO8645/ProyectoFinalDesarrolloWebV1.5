<?php

namespace Tests\Feature\Auth;

use App\Models\Existencia;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sesion;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\PermisoService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthTestCase;

class SesionTest extends AuthTestCase
{
    public function test_login_entrega_jwt_y_cookie_httponly_sin_exponer_secretos(): void
    {
        $usuario = Usuario::factory()->create();
        $respuesta = $this->login($usuario)->assertOk()->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 900)->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('usuario.password')->assertJsonMissingPath('usuario.codigo_hash');
        $cookie = $respuesta->getCookie(config('jwt.cookie'), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/api/auth', $cookie->getPath());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $datos = (array) JWT::decode($respuesta->json('access_token'), new Key(config('jwt.secret'), 'HS256'));
        $this->assertSame((string) $usuario->getKey(), $datos['sub']);
        $this->assertSame('access', $datos['token_use']);
        $sesion = Sesion::firstOrFail();
        $this->assertSame((string) $usuario->getKey(), $sesion->usuario_id);
        $this->assertSame(hash('sha256', explode('.', $cookie->getValue())[1]), $sesion->refresh_hash);
        $this->assertStringNotContainsString($cookie->getValue(), $sesion->toJson());
        $this->withToken($respuesta->json('access_token'))->getJson('/api/auth/me')->assertOk()->assertJsonPath('_id', (string) $usuario->getKey());
    }

    public function test_cookie_secure_se_respeta(): void
    {
        config(['jwt.cookie_secure' => true]);
        $usuario = Usuario::factory()->create();
        $respuesta = $this->login($usuario)->assertOk();
        $this->assertTrue($respuesta->getCookie(config('jwt.cookie'), false)->isSecure());
    }

    public function test_login_normaliza_nombre_de_usuario(): void
    {
        $usuario = Usuario::factory()->create(['usuario' => 'empleado.prueba']);
        $this->postJson('/api/auth/login', ['usuario' => ' EMPLEADO.PRUEBA ', 'password' => 'PruebaSegura2026!'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('usuario.usuario', $usuario->usuario);
    }

    public function test_credenciales_incorrectas_inactivo_y_sucursal_inactiva_devuelven_el_mismo_401(): void
    {
        $usuario = Usuario::factory()->create();
        $respuesta = ['message' => 'Usuario o contraseña incorrectos.'];
        $this->login($usuario, 'incorrecta')->assertUnauthorized()->assertExactJson($respuesta);
        $this->postJson('/api/auth/login', ['usuario' => 'inexistente', 'password' => 'incorrecta'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized()->assertExactJson($respuesta);
        $usuario->update(['activo' => false]);
        $this->login($usuario)->assertUnauthorized()->assertExactJson($respuesta);
        $usuario->update(['activo' => true]);
        Sucursal::findOrFail($usuario->sucursal_id)->update(['activo' => false]);
        $this->login($usuario)->assertUnauthorized()->assertExactJson($respuesta);
        $this->assertSame(0, Sesion::count());
    }

    public function test_login_limita_intentos_con_429(): void
    {
        $usuario = Usuario::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->login($usuario, 'incorrecta')->assertUnauthorized();
        }
        $this->login($usuario)->assertTooManyRequests();
        $this->assertSame(0, Sesion::count());
    }

    public function test_login_sin_datos_o_con_objeto_devuelve_422(): void
    {
        $this->postJson('/api/auth/login', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertUnprocessable()->assertJsonValidationErrors(['usuario', 'password']);
        $this->postJson('/api/auth/login', ['usuario' => ['objeto'], 'password' => 'incorrecta'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()->assertJsonValidationErrors('usuario');
    }

    public function test_origen_y_encabezado_son_obligatorios_para_sesion_con_cookie(): void
    {
        $usuario = Usuario::factory()->create();
        $datos = ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'];
        $this->postJson('/api/auth/login', $datos)->assertForbidden();
        $this->postJson('/api/auth/login', $datos, ['Origin' => 'https://sitio-ajeno.example', 'X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
        $this->postJson('/api/auth/refresh')->assertForbidden();
        $this->postJson('/api/auth/logout')->assertForbidden();
        $this->assertSame(0, Sesion::count());
    }

    public function test_cors_no_autoriza_origen_ajeno(): void
    {
        $respuesta = $this->options('/api/auth/refresh', [], ['Origin' => 'https://sitio-ajeno.example', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'X-Requested-With']);
        $this->assertNull($respuesta->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_access_expirado_se_renueva_y_refresh_rota_sin_extender_su_expiracion(): void
    {
        $this->freezeTime();
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $anterior = $this->cookie($login);
        $expiracion = Sesion::firstOrFail()->refresh_expires_at;
        $this->travel(16)->minutes();
        JWT::$timestamp = now()->timestamp;
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $nuevo = $this->refresh($anterior)->assertOk();
        $this->assertNotSame($anterior, $this->cookie($nuevo));
        $this->assertNotSame($login->json('access_token'), $nuevo->json('access_token'));
        $this->assertSame($expiracion, Sesion::firstOrFail()->refresh_expires_at);
        $this->withToken($nuevo->json('access_token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_reutilizar_refresh_anterior_revoca_la_sesion(): void
    {
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $renovado = $this->refresh($this->cookie($login))->assertOk();
        $this->refresh($this->cookie($login))->assertUnauthorized();
        $this->assertNotNull(Sesion::firstOrFail()->revoked_at);
        $this->withToken($renovado->json('access_token'))->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($renovado))->assertUnauthorized();
    }

    public function test_refresh_inventado_no_revoca_la_sesion_valida(): void
    {
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $id = explode('.', $this->cookie($login))[0];
        $this->refresh($id.'.'.str_repeat('0', 64))->assertUnauthorized();
        $this->assertNull(Sesion::firstOrFail()->revoked_at);
        $this->withToken($login->json('access_token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_refresh_expirado_o_sin_cookie_devuelve_401(): void
    {
        $this->postJson('/api/auth/refresh', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertUnauthorized();
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $this->travel(8)->days();
        $this->refresh($this->cookie($login))->assertUnauthorized();
    }

    public function test_logout_revoca_access_y_refresh_y_elimina_cookie_aunque_access_expire(): void
    {
        $this->freezeTime();
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $cookie = $this->cookie($login);
        $this->travel(16)->minutes();
        JWT::$timestamp = now()->timestamp;
        $this->withToken($login->json('access_token'))->withCredentials()->withUnencryptedCookie(config('jwt.cookie'), $cookie)
            ->postJson('/api/auth/logout', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertCookieExpired(config('jwt.cookie'));
        $this->assertNotNull(Sesion::firstOrFail()->revoked_at);
        $this->refresh($cookie)->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    public function test_logout_invalida_access_inmediatamente_sin_esperar_expiracion(): void
    {
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $this->withToken($login->json('access_token'))->postJson('/api/auth/logout', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->refresh($this->cookie($login))->assertUnauthorized();
    }

    public function test_jwt_alterado_audiencia_incorrecta_y_sesion_de_otro_usuario_devuelven_401(): void
    {
        $usuario = Usuario::factory()->create();
        $login = $this->login($usuario)->assertOk();
        $token = $login->json('access_token');
        $partes = explode('.', $token);
        $partes[2][0] = $partes[2][0] === 'a' ? 'b' : 'a';
        $this->withToken(implode('.', $partes))->getJson('/api/auth/me')->assertUnauthorized();
        $datos = (array) JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        $datos['aud'] = 'otra-aplicacion';
        $this->withToken(JWT::encode($datos, config('jwt.secret'), 'HS256'))->getJson('/api/auth/me')->assertUnauthorized();
        $datos['aud'] = config('jwt.audience');
        $datos['sub'] = (string) Usuario::factory()->create()->getKey();
        $this->withToken(JWT::encode($datos, config('jwt.secret'), 'HS256'))->getJson('/api/auth/me')->assertUnauthorized();
        unset($datos['exp']);
        $this->withToken(JWT::encode($datos, config('jwt.secret'), 'HS256'))->getJson('/api/auth/me')->assertUnauthorized();
    }

    #[DataProvider('rutasProtegidas')]
    public function test_api_sin_token_devuelve_401(string $ruta): void
    {
        $this->getJson($ruta)->assertUnauthorized();
    }

    public static function rutasProtegidas(): array
    {
        return array_map(fn ($ruta) => [$ruta], ['/api/auth/me', '/api/sucursales', '/api/proveedores', '/api/clientes', '/api/productos', '/api/admin/usuarios', '/api/catalogos/sucursales']);
    }

    public function test_empleado_sin_permiso_no_puede_leer_escribir_ni_escalar_privilegios(): void
    {
        $empleado = Usuario::factory()->create();
        $login = $this->login($empleado)->assertOk();
        $this->withToken($login->json('access_token'));
        foreach (['/api/sucursales', '/api/proveedores', '/api/clientes', '/api/productos', '/api/admin/usuarios'] as $ruta) {
            $this->getJson($ruta)->assertForbidden();
            $this->postJson($ruta, ['rol' => 'superusuario'])->assertForbidden();
            $this->putJson($ruta.'/000000000000000000000000', ['rol' => 'superusuario'])->assertForbidden();
            $this->deleteJson($ruta.'/000000000000000000000000')->assertForbidden();
        }
        $empleado->update(['modulos' => ['usuarios']]);
        $this->getJson('/api/admin/usuarios')->assertForbidden();
    }

    public function test_catalogo_permitido_expone_solo_existencias_y_sucursal_del_empleado(): void
    {
        $empleado = Usuario::factory()->create(['modulos' => ['catalogo']]);
        $otra = Sucursal::create(['nombre' => 'Otra sucursal', 'activo' => true]);
        Proveedor::factory()->create(['correo' => 'privado@example.com']);
        $producto = Producto::factory()->create();
        Existencia::create(['producto_id' => (string) $producto->getKey(), 'sucursal_id' => $empleado->sucursal_id, 'cantidad' => 3]);
        Existencia::create(['producto_id' => (string) $producto->getKey(), 'sucursal_id' => (string) $otra->getKey(), 'cantidad' => 7]);
        $login = $this->login($empleado)->assertOk();
        $this->withToken($login->json('access_token'))->getJson('/api/productos')->assertOk()->assertJsonPath('0.existencia', 3);
        $this->getJson('/api/productos/'.$producto->getKey())->assertOk()->assertJsonPath('existencia', 3);
        $this->getJson('/api/productos?sucursal_id='.$otra->getKey())->assertForbidden();
        $this->getJson('/api/catalogos/sucursales')->assertOk()->assertJsonCount(1)->assertJsonPath('0._id', $empleado->sucursal_id);
        $this->getJson('/api/catalogos/proveedores')->assertOk()->assertJsonMissingPath('0.correo')->assertJsonMissingPath('0.telefono');
        $this->getJson('/api/sucursales')->assertForbidden();
        $this->getJson('/api/proveedores')->assertForbidden();
    }

    public function test_modulo_desconocido_se_deniega_incluso_al_superusuario(): void
    {
        Route::get('/api/prueba-permiso', fn () => response()->json(['ok' => true]))->middleware(['auth.jwt', 'modulo:desconocido']);
        $super = Usuario::factory()->create(['rol' => 'superusuario']);
        $login = $this->login($super)->assertOk();
        $this->withToken($login->json('access_token'))->getJson('/api/prueba-permiso')->assertForbidden();
        $this->assertFalse(app(PermisoService::class)->puede(null, 'catalogo'));
    }

    public function test_codigo_facturacion_se_valida_sin_exponer_hash(): void
    {
        $empleado = Usuario::factory()->create(['modulos' => ['ventas'], 'codigo_hash' => Hash::make('1234')]);
        $login = $this->login($empleado)->assertOk()->assertJsonPath('usuario.tiene_codigo', true);
        $this->withToken($login->json('access_token'))->postJson('/api/auth/verificar-codigo', ['codigo' => '0000'])->assertUnprocessable();
        $this->postJson('/api/auth/verificar-codigo', ['codigo' => '1234'])->assertOk()->assertExactJson(['ok' => true]);
    }
}
