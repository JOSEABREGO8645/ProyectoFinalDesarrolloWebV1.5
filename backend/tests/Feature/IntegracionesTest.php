<?php

namespace Tests\Feature;

use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use App\Models\Venta;
use App\Repositories\UsuarioRepository;
use App\Services\OperacionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\MongoTestCase;

class IntegracionesTest extends MongoTestCase
{
    private Usuario $admin;

    private string $ingreso;

    private string $venta;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'integraciones.facturacion' => ['url' => 'http://factura.test', 'token' => str_repeat('f', 32)], 'integraciones.pagos' => ['url' => 'http://pago.test', 'token' => str_repeat('p', 32)]]);
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php'])->assertExitCode(0)->run();
        $this->admin = Usuario::factory()->create(['rol' => 'superusuario', 'codigo_hash' => Hash::make('4321')]);
        $this->entrar($this->admin);
        $proveedor = Proveedor::factory()->create(['activo' => true]);
        $producto = Producto::factory()->create(['activo' => true, 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '0.47', 'precio_venta' => '1.01']);
        $datos = ['operacion_id' => (string) Str::uuid(), 'motivo' => 'Prueba de integración', 'proveedor_id' => (string) $proveedor->getKey(), 'items' => [['producto_id' => (string) $producto->getKey(), 'cantidad' => 5]]];
        $this->ingreso = $this->postJson('/api/ingresos', $datos)->assertCreated()->json('_id');
        $datos['operacion_id'] = (string) Str::uuid();
        $datos['codigo'] = '4321';
        $datos['efectivo'] = '10.00';
        $datos['items'][0]['cantidad'] = 1;
        $this->venta = $this->postJson('/api/ventas', $datos)->assertCreated()->json('_id');
    }

    private function entrar(Usuario $usuario): void
    {
        $r = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($r->json('access_token'));
    }

    private function respuesta($r, array $cambios = []): array
    {
        $tipo = str_contains($r->url(), 'factura.test') ? 'facturacion' : 'pagos';
        $clave = $r->header('Idempotency-Key')[0];

        return array_merge(['entorno' => 'simulado', 'tipo' => $tipo, 'estado' => 'generado', 'idempotency_key' => $clave, 'documento_id' => $r['documento_id'], 'moneda' => 'GTQ', 'total_centavos' => $r['total_centavos'],
            'referencia' => ($tipo === 'facturacion' ? 'FAC-DEMO-' : 'PAGO-DEMO-').strtoupper(substr($clave, 0, 20)), 'emitido_at' => '2026-10-24T16:00:00.000Z', 'validez' => $tipo === 'facturacion' ? 'SIN_VALOR_FISCAL' : 'SIN_MOVIMIENTO_BANCARIO'], $cambios);
    }

    public function test_servicios_distintos_usan_importes_originales_y_no_cambian_inventario(): void
    {
        $antes = Existencia::firstOrFail()->cantidad;
        Http::fake(fn ($r) => Http::response($this->respuesta($r)));
        foreach ([['ventas', $this->venta, 'factura-prueba', 'facturacion', 101], ['ingresos', $this->ingreso, 'pago-prueba', 'pagos', 235]] as [$tipo,$id,$accion,$campo,$total]) {
            $ruta = '/api/'.$tipo.'/'.$id.'/'.$accion;
            $primero = $this->postJson($ruta, ['total_centavos' => 1, 'codigo' => '0000'])->assertOk()->assertJsonPath('estado', 'completada')->assertJsonPath('total_centavos', $total)->json();
            $this->assertSame($primero, $this->postJson($ruta)->assertOk()->json());
            $this->getJson('/api/'.$tipo.'/'.$id)->assertOk()->assertJsonPath('integraciones.'.$campo.'.referencia', $primero['referencia'])->assertJsonMissingPath('api_en_curso');
        }
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->url() === 'http://factura.test/v1/facturas' && $r->hasHeader('Authorization', 'Bearer '.str_repeat('f', 32)) && ! isset($r['codigo']) && count($r->data()) === 7 && $r['total_centavos'] === 101);
        $this->assertSame($antes, Existencia::firstOrFail()->cantidad);
        $this->assertSame(2, Movimiento::count());
        $this->assertSame('emitida', Venta::findOrFail($this->venta)->estado);
    }

    public function test_timeout_permite_reintento_con_la_misma_clave_sin_duplicar(): void
    {
        $intento = 0;
        Http::fake(function ($r) use (&$intento) {
            if ($intento++ === 0) {
                throw new ConnectionException;
            }

            return Http::response($this->respuesta($r));
        });
        $ruta = '/api/ventas/'.$this->venta.'/factura-prueba';
        $this->postJson($ruta)->assertStatus(503);
        $this->getJson('/api/ventas/'.$this->venta)->assertJsonPath('integraciones.facturacion.estado', 'pendiente')->assertJsonMissingPath('api_en_curso');
        $clave = hash('sha256', 'libreria:prueba:facturacion:'.$this->venta);
        $this->postJson($ruta)->assertOk();
        Http::assertSent(fn ($r) => $r->header('Idempotency-Key')[0] === $clave);
        $this->assertSame(2, Movimiento::count());
    }

    public function test_respuesta_inconsistente_o_real_no_se_confirma(): void
    {
        $cambio = [];
        Http::fake(function ($r) use (&$cambio) {
            return Http::response($this->respuesta($r, $cambio));
        });
        foreach ([['total_centavos' => 1], ['entorno' => 'produccion'], ['validez' => 'FEL'], ['documento_id' => str_repeat('a', 24)], ['referencia' => '<script>'], ['emitido_at' => 'ayer'], ['emitido_at' => '2026-02-31T16:00:00.000Z']] as $cambio) {
            $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertStatus(502);
            $this->assertSame('pendiente', Venta::findOrFail($this->venta)->integraciones['facturacion']['estado']);
        }
    }

    public function test_rechazos_y_errores_remotos_no_filtran_detalles(): void
    {
        $remoto = 422;
        Http::fake(function ($r) use (&$remoto) {
            return Http::response(['error' => 'clave_secreta_remota'], $remoto);
        });
        foreach ([422 => 422, 401 => 503, 500 => 503, 302 => 503] as $remoto => $local) {
            $r = $this->postJson('/api/ingresos/'.$this->ingreso.'/pago-prueba')->assertStatus($local);
            $this->assertStringNotContainsString('clave_secreta_remota', $r->getContent());
        }
        $this->assertSame(2, Movimiento::count());
    }

    public function test_reserva_excluye_solicitudes_en_curso_y_recupera_una_vencida(): void
    {
        Venta::where('_id', $this->venta)->update(['api_en_curso.facturacion' => ['token' => 'otro', 'vence' => now()->timestamp + 30]]);
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertConflict();
        Http::assertNothingSent();
        Venta::where('_id', $this->venta)->update(['api_en_curso.facturacion.vence' => now()->timestamp - 1]);
        Http::fake(fn ($r) => Http::response($this->respuesta($r)));
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertOk();
    }

    public function test_anular_durante_la_llamada_preserva_respuesta_historica_sin_bloquear_stock(): void
    {
        Http::fake(function ($r) {
            app(OperacionService::class)->finalizar('ventas', $this->venta, 'anular', 'Anulación concurrente', $this->admin);

            return Http::response($this->respuesta($r));
        });
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertOk();
        $this->getJson('/api/ventas/'.$this->venta)->assertJsonPath('estado', 'anulado')->assertJsonPath('integraciones.facturacion.estado', 'completada');
        $this->assertSame(5, Existencia::firstOrFail()->cantidad);
        $this->assertSame(3, Movimiento::count());
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertConflict();
        Http::assertSentCount(1);
    }

    public function test_permisos_sucursal_ids_y_anulacion_se_validan_antes_del_servicio_externo(): void
    {
        foreach (['invalido', str_repeat('f', 24)] as $id) {
            $this->postJson('/api/ventas/'.$id.'/factura-prueba')->assertNotFound();
        }
        $this->postJson('/api/ventas/'.$this->venta.'/anular', ['motivo' => 'Anulación de prueba'])->assertOk();
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertConflict();
        $usuario = Usuario::factory()->create(['rol' => 'empleado', 'modulos' => []]);
        $this->entrar($usuario);
        $this->postJson('/api/ingresos/'.$this->ingreso.'/pago-prueba')->assertForbidden();
        $usuario->update(['modulos' => ['ingresos']]);
        $this->postJson('/api/ingresos/'.$this->ingreso.'/pago-prueba')->assertNotFound();
        $this->flushHeaders();
        $this->postJson('/api/ventas/'.$this->venta.'/factura-prueba')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_falta_configuracion_no_reserva_documento_y_autor_conserva_referencia(): void
    {
        config(['integraciones.pagos.token' => '']);
        $this->postJson('/api/ingresos/'.$this->ingreso.'/pago-prueba')->assertStatus(503);
        $this->assertNull(Ingreso::findOrFail($this->ingreso)->api_en_curso);
        $otro = Usuario::factory()->create();
        Ingreso::where('_id', $this->ingreso)->update(['integraciones.pagos' => ['solicitado_por_id' => (string) $otro->getKey()]]);
        $this->assertTrue(app(UsuarioRepository::class)->enUso((string) $otro->getKey()));
        Http::assertNothingSent();
    }
}
