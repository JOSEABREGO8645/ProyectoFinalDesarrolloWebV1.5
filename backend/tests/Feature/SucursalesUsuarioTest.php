<?php

namespace Tests\Feature;

use App\Models\Existencia;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Support\Str;
use Tests\MongoTestCase;

class SucursalesUsuarioTest extends MongoTestCase
{
    private Usuario $admin;

    private Usuario $cajero;

    private string $origen;

    private string $destino;

    private string $ajena;

    private function preparar(): void
    {
        $this->artisan('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php'])->assertExitCode(0)->run();
        $this->admin = Usuario::factory()->create(['rol' => 'superusuario']);
        $this->origen = $this->admin->sucursal_id;
        $this->destino = (string) Sucursal::create(['nombre' => 'Turno destino', 'activo' => true])->getKey();
        $this->ajena = (string) Sucursal::create(['nombre' => 'Sucursal no asignada', 'activo' => true])->getKey();
        $this->cajero = app(UsuarioService::class)->agregar($this->datos());
        $this->entrar($this->cajero);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Cajero rotativo', 'usuario' => 'rotativo', 'password' => 'RotativoSeguro2026!', 'codigo' => '4321', 'rol' => 'empleado',
            'sucursal_id' => $this->origen, 'sucursal_ids' => [$this->origen, $this->destino], 'modulos' => ['ventas', 'ingresos', 'traslados', 'catalogo', 'reportes'], 'activo' => true], $extra);
    }

    private function entrar(Usuario $usuario)
    {
        $r = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => $usuario->usuario === 'rotativo' ? 'RotativoSeguro2026!' : 'PruebaSegura2026!'])->assertOk();
        $this->withToken($r->json('access_token'));

        return $r;
    }

    private function cambiar(string $id): void
    {
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => $id])->assertOk()->assertJsonPath('usuario.sucursal_id', $id);
    }

    public function test_asignacion_multiple_y_principal_se_validan_y_conservan_en_edicion(): void
    {
        $this->preparar();
        $this->entrar($this->admin);
        $r = $this->postJson('/api/admin/usuarios', $this->datos(['usuario' => 'nuevo']))->assertCreated();
        $id = $r->json('datos._id');
        $this->getJson('/api/admin/usuarios/'.$id)->assertJsonPath('sucursal_ids', [$this->origen, $this->destino]);
        $datos = $this->datos(['usuario' => 'nuevo', 'nombre' => 'Nombre actualizado']);
        unset($datos['password'],$datos['sucursal_ids'],$datos['codigo']);
        $this->putJson('/api/admin/usuarios/'.$id, $datos)->assertOk();
        $this->assertSame([$this->origen, $this->destino], Usuario::findOrFail($id)->sucursalesAsignadas());
        foreach ([[], [$this->destino], [$this->origen, str_repeat('f', 24)], [$this->origen, $this->origen]] as $ids) {
            $this->postJson('/api/admin/usuarios', $this->datos(['usuario' => 'rechazado', 'sucursal_ids' => $ids]))->assertUnprocessable();
        }
    }

    public function test_cambio_es_por_sesion_y_no_altera_principal_ni_otra_sesion(): void
    {
        $this->preparar();
        $otra = $this->entrar($this->cajero)->json('access_token');
        $this->entrar($this->cajero);
        $this->cambiar($this->destino);
        $this->getJson('/api/auth/me')->assertJsonPath('sucursal_id', $this->destino)->assertJsonPath('sucursal_principal_id', $this->origen);
        $this->assertSame($this->origen, $this->cajero->fresh()->sucursal_id);
        $this->withToken($otra)->getJson('/api/auth/me')->assertJsonPath('sucursal_id', $this->origen);
    }

    public function test_renovacion_conserva_sucursal_activa_y_rechaza_formulario_de_otra_pestana(): void
    {
        $this->preparar();
        $login = $this->entrar($this->cajero);
        $cookie = $login->getCookie(config('jwt.cookie'), false)->getValue();
        $this->cambiar($this->destino);
        $this->withHeader('X-Sucursal-Id', $this->origen)->getJson('/api/productos')->assertConflict()->assertJsonPath('code', 'SUCURSAL_CAMBIADA');
        $this->withoutHeader('X-Sucursal-Id');
        $this->withCredentials()->withUnencryptedCookie(config('jwt.cookie'), $cookie)->postJson('/api/auth/refresh')->assertOk()->assertJsonPath('usuario.sucursal_id', $this->destino);
    }

    public function test_no_permite_sucursal_no_asignada_inactiva_o_id_invalido(): void
    {
        $this->preparar();
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => $this->ajena])->assertForbidden();
        Sucursal::where('_id', $this->destino)->update(['activo' => false]);
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => $this->destino])->assertForbidden();
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => 'incorrecto'])->assertUnprocessable();
        $this->getJson('/api/auth/me')->assertJsonPath('sucursal_id', $this->origen);
        $this->flushHeaders();
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => $this->destino])->assertUnauthorized();
    }

    public function test_cajero_vende_con_saldo_de_sucursal_activa_y_traslado_solo_se_acepta_en_destino(): void
    {
        $this->preparar();
        $proveedor = Proveedor::factory()->create(['activo' => true]);
        $producto = Producto::factory()->create(['proveedor_id' => (string) $proveedor->getKey(), 'activo' => true, 'precio_costo' => '1.00', 'precio_venta' => '2.00']);
        $item = ['producto_id' => (string) $producto->getKey(), 'cantidad' => 5];
        $this->postJson('/api/ingresos', ['operacion_id' => (string) Str::uuid(), 'proveedor_id' => (string) $proveedor->getKey(), 'motivo' => 'Ingreso rotativo', 'items' => [$item]])->assertCreated();
        $item['cantidad'] = 2;
        $traslado = $this->postJson('/api/traslados', ['operacion_id' => (string) Str::uuid(), 'destino_id' => $this->destino, 'motivo' => 'Enviar a destino', 'items' => [$item]])->assertCreated()->json('_id');
        $this->postJson('/api/traslados/'.$traslado.'/confirmar', ['motivo' => 'No corresponde'])->assertForbidden();
        $this->cambiar($this->destino);
        $item['cantidad'] = 1;
        $venta = ['operacion_id' => (string) Str::uuid(), 'codigo' => '4321', 'efectivo' => '10.00', 'items' => [$item]];
        $this->postJson('/api/ventas', $venta)->assertConflict();
        $this->postJson('/api/traslados/'.$traslado.'/anular', ['motivo' => 'Desde destino'])->assertForbidden();
        $this->postJson('/api/traslados/'.$traslado.'/confirmar', ['motivo' => 'Recibido en destino'])->assertOk();
        $this->postJson('/api/ventas', $venta)->assertCreated()->assertJsonPath('sucursal_id', $this->destino);
        $this->assertSame(3, Existencia::where('sucursal_id', $this->origen)->value('cantidad'));
        $this->assertSame(1, Existencia::where('sucursal_id', $this->destino)->value('cantidad'));
        $this->assertSame(4, Movimiento::count());
        $this->postJson('/api/traslados/'.$traslado.'/confirmar', ['motivo' => 'Repetición'])->assertConflict();
    }

    public function test_superusuario_tambien_debe_alternar_al_destino_para_confirmar(): void
    {
        $this->preparar();
        $this->entrar($this->admin);
        $proveedor = Proveedor::factory()->create(['activo' => true]);
        $p = Producto::factory()->create(['activo' => true, 'proveedor_id' => (string) $proveedor->getKey()]);
        $base = ['operacion_id' => (string) Str::uuid(), 'proveedor_id' => (string) $proveedor->getKey(), 'motivo' => 'Inventario superusuario', 'items' => [['producto_id' => (string) $p->getKey(), 'cantidad' => 3]]];
        $this->postJson('/api/ingresos', $base)->assertCreated();
        $base['operacion_id'] = (string) Str::uuid();
        $base['destino_id'] = $this->destino;
        $id = $this->postJson('/api/traslados', $base)->assertCreated()->json('_id');
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Misma sucursal'])->assertForbidden();
        $this->cambiar($this->destino);
        $this->postJson('/api/traslados/'.$id.'/anular', ['motivo' => 'Sucursal destino'])->assertForbidden();
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Sucursal destino'])->assertOk();
    }

    public function test_cambiar_asignaciones_revoca_sesiones_y_sucursal_secundaria_en_uso_no_se_desactiva(): void
    {
        $this->preparar();
        $token = $this->entrar($this->cajero)->json('access_token');
        $this->entrar($this->admin);
        $this->putJson('/api/sucursales/'.$this->destino, ['nombre' => 'Turno destino', 'activo' => false])->assertConflict();
        $datos = $this->datos(['sucursal_ids' => [$this->origen]]);
        unset($datos['password'],$datos['codigo']);
        $this->putJson('/api/admin/usuarios/'.$this->cajero->getKey(), $datos)->assertOk();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_usuario_antiguo_sin_array_conserva_su_acceso(): void
    {
        $this->preparar();
        $usuario = Usuario::factory()->create(['rol' => 'empleado', 'sucursal_id' => $this->origen]);
        $login = $this->entrar($usuario);
        $this->assertSame([$this->origen], $login->json('usuario.sucursal_ids'));
        $this->assertCount(1, $login->json('usuario.sucursales'));
    }
}
