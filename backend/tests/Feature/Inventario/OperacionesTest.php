<?php

namespace Tests\Feature\Inventario;

use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Traslado;
use App\Models\Usuario;
use App\Models\Venta;
use App\Repositories\MovimientoRepository;
use App\Services\InventarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\MongoTestCase;

class OperacionesTest extends MongoTestCase
{
    private Usuario $admin;

    private Producto $producto;

    private Proveedor $proveedor;

    private Sucursal $destino;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php'])->assertExitCode(0)->run();
        $this->admin = Usuario::factory()->create(['rol' => 'superusuario', 'codigo_hash' => Hash::make('4321')]);
        $this->proveedor = Proveedor::factory()->create(['activo' => true]);
        $this->producto = Producto::factory()->create(['codigo' => '000007-1', 'proveedor_id' => (string) $this->proveedor->getKey(), 'precio_costo' => '0.47', 'precio_venta' => '1.01', 'activo' => true]);
        $this->destino = Sucursal::create(['nombre' => 'Destino de pruebas', 'activo' => true]);
        $this->entrar($this->admin);
    }

    private function entrar(Usuario $usuario): void
    {
        $login = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($login->json('access_token'));
    }

    private function datos(string $tipo, int $cantidad = 5): array
    {
        return array_merge(['operacion_id' => (string) Str::uuid(), 'motivo' => 'Documento de prueba',
            'items' => [['producto_id' => (string) $this->producto->getKey(), 'cantidad' => $cantidad]]], match ($tipo) {
                'ingresos' => ['proveedor_id' => (string) $this->proveedor->getKey()],
                'ventas' => ['codigo' => '4321', 'efectivo' => '100.00'],
                'traslados' => ['destino_id' => (string) $this->destino->getKey()],
            });
    }

    private function ingresar(int $cantidad = 5): string
    {
        return $this->postJson('/api/ingresos', $this->datos('ingresos', $cantidad))->assertCreated()->json('_id');
    }

    private function stock(?string $sucursal = null): int
    {
        return (int) (Existencia::where('producto_id', (string) $this->producto->getKey())->where('sucursal_id', $sucursal ?? $this->admin->sucursal_id)->value('cantidad') ?? 0);
    }

    public function test_ingreso_conserva_codigos_y_registra_kardex(): void
    {
        $id = $this->ingresar(7);
        $this->getJson('/api/ingresos/'.$id)->assertOk()->assertJsonPath('items.0.codigo', '000007-1')->assertJsonPath('total_centavos', 329)->assertJsonMissingPath('huella');
        $this->assertSame(7, $this->stock());
        $this->getJson('/api/inventario/movimientos')->assertOk()->assertJsonCount(1)->assertJsonPath('0.antes', 0)->assertJsonPath('0.despues', 7)->assertJsonPath('0.documento_id', $id);
        $this->getJson('/api/productos')->assertOk()->assertJsonPath('0.existencia', 7);
    }

    public function test_venta_calcula_importes_del_servidor_y_cambio_en_centavos(): void
    {
        $this->ingresar();
        $datos = array_merge($this->datos('ventas', 3), ['total_centavos' => 0, 'efectivo' => '5.00']);
        $venta = $this->postJson('/api/ventas', $datos)->assertCreated()->assertJsonPath('total_centavos', 303)->assertJsonPath('cambio_centavos', 197)->assertJsonPath('items.0.precio_centavos', 101)->assertJsonMissingPath('codigo')->assertJsonMissingPath('codigo_hash');
        $this->assertSame(2, $this->stock());
        $movimiento = Movimiento::where('tipo', 'venta')->firstOrFail();
        $this->assertSame(-3, $movimiento->cantidad);
        $this->assertSame($venta->json('_id'), $movimiento->documento_id);
        $this->assertArrayNotHasKey('codigo', Venta::firstOrFail()->getAttributes());
        $this->assertArrayNotHasKey('codigo_hash', Venta::firstOrFail()->getAttributes());
    }

    public function test_segunda_linea_sin_stock_revierte_documento_contador_y_kardex(): void
    {
        $this->ingresar();
        $otro = Producto::factory()->create(['activo' => true]);
        $datos = $this->datos('ventas', 4);
        $datos['items'][] = ['producto_id' => (string) $otro->getKey(), 'cantidad' => 1];
        $this->postJson('/api/ventas', $datos)->assertConflict();
        $this->assertSame(5, $this->stock());
        $this->assertSame(0, Venta::count());
        $this->assertSame(1, Movimiento::count());
        $this->assertSame(0, DB::connection('mongodb')->table('contadores')->where('_id', 'ventas:'.$this->admin->sucursal_id)->count());
        $this->postJson('/api/ventas', $this->datos('ventas', 1))->assertCreated()->assertJsonPath('numero', 1);
    }

    public function test_error_al_guardar_kardex_revierte_el_ingreso_y_sus_existencias(): void
    {
        $this->mock(MovimientoRepository::class)->shouldReceive('crear')->once()->andThrow(new \RuntimeException('Fallo de almacenamiento simulado'));
        $this->postJson('/api/ingresos', $this->datos('ingresos'))->assertStatus(500);
        $this->assertSame(0, Existencia::count());
        $this->assertSame(0, Ingreso::count());
        $this->assertSame(0, Movimiento::count());
    }

    public function test_reintento_identico_no_duplica_y_conserva_precio_original(): void
    {
        $datos = $this->datos('ingresos');
        $id = $this->postJson('/api/ingresos', $datos)->assertCreated()->json('_id');
        $this->producto->update(['precio_costo' => '9.99']);
        $this->postJson('/api/ingresos', $datos)->assertCreated()->assertJsonPath('_id', $id)->assertJsonPath('total_centavos', 235);
        $this->assertSame(5, $this->stock());
        $this->assertSame(1, Ingreso::count());
        $this->assertSame(1, Movimiento::count());
        $datos['items'][0]['cantidad'] = 6;
        $this->postJson('/api/ingresos', $datos)->assertConflict();
        $this->assertSame(5, $this->stock());
    }

    #[DataProvider('entradasInvalidas')]
    public function test_valida_encabezado_y_detalle(array $cambios, string $campo): void
    {
        $this->postJson('/api/ingresos', array_replace_recursive($this->datos('ingresos'), $cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertSame(0, Ingreso::count());
    }

    public static function entradasInvalidas(): array
    {
        return [
            [['operacion_id' => 'sin-clave'], 'operacion_id'],
            [['items' => null], 'items'],
            [['items' => [['cantidad' => 0]]], 'items.0.cantidad'],
            [['items' => [['cantidad' => 1.5]]], 'items.0.cantidad'],
            [['items' => [['cantidad' => 100001]]], 'items.0.cantidad'],
            [['items' => [['producto_id' => ['$ne' => null]]]], 'items.0.producto_id'],
            [['items' => [['precio_centavos' => 1]]], 'items.0'],
            [['motivo' => 'a'], 'motivo'],
        ];
    }

    public function test_rechaza_lineas_duplicadas_y_producto_inactivo(): void
    {
        $datos = $this->datos('ingresos');
        $datos['items'][] = $datos['items'][0];
        $this->postJson('/api/ingresos', $datos)->assertUnprocessable()->assertJsonValidationErrors('items.0.producto_id');
        $this->producto->update(['activo' => false]);
        $this->postJson('/api/ingresos', $this->datos('ingresos'))->assertUnprocessable();
        $this->assertSame(0, Existencia::count());
    }

    public function test_proveedor_inactivo_o_incorrecto_no_ingresa_stock(): void
    {
        $otro = Proveedor::factory()->create(['activo' => true]);
        $datos = $this->datos('ingresos');
        $datos['proveedor_id'] = (string) $otro->getKey();
        $this->postJson('/api/ingresos', $datos)->assertUnprocessable();
        $this->proveedor->update(['activo' => false]);
        $this->postJson('/api/ingresos', $this->datos('ingresos'))->assertUnprocessable();
        $this->assertSame(0, Ingreso::count());
    }

    public function test_pin_correcto_se_exige_al_guardar_y_efectivo_debe_alcanzar(): void
    {
        $this->ingresar();
        $this->postJson('/api/auth/verificar-codigo', ['codigo' => '4321'])->assertOk();
        $datos = $this->datos('ventas', 1);
        $datos['codigo'] = '0000';
        $this->postJson('/api/ventas', $datos)->assertUnprocessable();
        $datos['codigo'] = '4321';
        $datos['efectivo'] = '1.00';
        $this->postJson('/api/ventas', $datos)->assertUnprocessable();
        $this->assertSame(5, $this->stock());
        $this->assertSame(0, Venta::count());
    }

    public function test_traslado_descuenta_al_enviar_y_suma_una_sola_vez_al_confirmar(): void
    {
        $this->ingresar();
        $id = $this->postJson('/api/traslados', $this->datos('traslados', 3))->assertCreated()->json('_id');
        $this->assertSame(2, $this->stock());
        $this->assertSame(0, $this->stock((string) $this->destino->getKey()));
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => (string) $this->destino->getKey()])->assertOk();
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Recibido completo'])->assertOk()->assertJsonPath('estado', 'confirmado');
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Segundo intento'])->assertConflict();
        $this->postJson('/api/traslados/'.$id.'/anular', ['motivo' => 'Intento tardío'])->assertForbidden();
        $this->assertSame(2, $this->stock());
        $this->assertSame(3, $this->stock((string) $this->destino->getKey()));
        $this->assertSame(3, Movimiento::count());
    }

    public function test_anular_envio_devuelve_origen_y_no_permite_recepcion(): void
    {
        $this->ingresar();
        $id = $this->postJson('/api/traslados', $this->datos('traslados', 3))->assertCreated()->json('_id');
        $this->postJson('/api/traslados/'.$id.'/anular', ['motivo' => 'Envío cancelado'])->assertOk()->assertJsonPath('estado', 'anulado');
        $this->postJson('/api/auth/sucursal', ['sucursal_id' => (string) $this->destino->getKey()])->assertOk();
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Recepción tardía'])->assertConflict();
        $this->assertSame(5, $this->stock());
        $this->assertSame(0, $this->stock((string) $this->destino->getKey()));
    }

    public function test_rechaza_traslado_a_misma_sucursal_o_destino_inactivo(): void
    {
        $datos = $this->datos('traslados');
        $datos['destino_id'] = $this->admin->sucursal_id;
        $this->postJson('/api/traslados', $datos)->assertUnprocessable();
        $this->destino->update(['activo' => false]);
        $this->postJson('/api/traslados', $this->datos('traslados'))->assertUnprocessable();
        $this->assertSame(0, Traslado::count());
    }

    public function test_anular_venta_reintegra_y_anular_ingreso_consumido_revierte_sin_cambios(): void
    {
        $ingreso = $this->ingresar();
        $venta = $this->postJson('/api/ventas', $this->datos('ventas', 3))->assertCreated()->json('_id');
        $this->postJson('/api/ingresos/'.$ingreso.'/anular', ['motivo' => 'No debe alcanzar'])->assertConflict();
        $this->assertSame('registrado', Ingreso::findOrFail($ingreso)->estado);
        $this->assertSame(2, $this->stock());
        $this->postJson('/api/ventas/'.$venta.'/anular', ['motivo' => 'Venta cancelada'])->assertOk()->assertJsonPath('estado', 'anulado');
        $this->postJson('/api/ventas/'.$venta.'/anular', ['motivo' => 'Intento repetido'])->assertConflict();
        $this->assertSame(5, $this->stock());
        $this->producto->update(['activo' => false]);
        $this->postJson('/api/ingresos/'.$ingreso.'/anular', ['motivo' => 'Ingreso cancelado'])->assertOk();
        $this->assertSame(0, $this->stock());
        $this->assertSame(4, Movimiento::count());
    }

    public function test_empleado_solo_opera_su_sucursal_y_selectores_no_exigen_catalogo(): void
    {
        $empleado = Usuario::factory()->create(['sucursal_id' => $this->admin->sucursal_id, 'modulos' => ['ingresos']]);
        $this->entrar($empleado);
        $this->getJson('/api/catalogos/productos')->assertOk()->assertJsonPath('0.precio_costo', '0.47');
        $this->getJson('/api/productos')->assertForbidden();
        $this->getJson('/api/ventas')->assertForbidden();
        $this->getJson('/api/ingresos?sucursal_id='.$this->destino->getKey())->assertForbidden();
        $datos = $this->datos('ingresos');
        $datos['sucursal_id'] = (string) $this->destino->getKey();
        $this->postJson('/api/ingresos', $datos)->assertForbidden();
        $id = $this->ingresar();
        $this->postJson('/api/ingresos/'.$id.'/anular', ['motivo' => 'Sin autorización'])->assertForbidden();
        $this->getJson('/api/inventario/movimientos?sucursal_id='.$this->destino->getKey())->assertForbidden();
        $this->getJson('/api/inventario/movimientos')->assertOk()->assertJsonCount(1);
    }

    public function test_permiso_de_traslado_no_permite_confirmar_en_origen_ni_anular_en_destino(): void
    {
        $this->ingresar();
        $id = $this->postJson('/api/traslados', $this->datos('traslados', 2))->assertCreated()->json('_id');
        $origen = Usuario::factory()->create(['sucursal_id' => $this->admin->sucursal_id, 'modulos' => ['traslados']]);
        $this->entrar($origen);
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Desde el origen'])->assertForbidden();
        $this->getJson('/api/catalogos/destinos')->assertOk()->assertJsonCount(2);
        $empleado = Usuario::factory()->create(['sucursal_id' => (string) $this->destino->getKey(), 'modulos' => ['traslados']]);
        $this->entrar($empleado);
        $this->getJson('/api/traslados')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/traslados/'.$id.'/anular', ['motivo' => 'Desde el destino'])->assertForbidden();
        $this->postJson('/api/traslados/'.$id.'/confirmar', ['motivo' => 'Recibido completo'])->assertOk();
        $tercero = Usuario::factory()->create(['modulos' => ['traslados']]);
        $this->entrar($tercero);
        $this->getJson('/api/traslados/'.$id)->assertNotFound();
        $this->getJson('/api/traslados')->assertOk()->assertJsonCount(0);
    }

    public function test_id_inexistente_sin_sesion_y_sin_permiso(): void
    {
        foreach (['ingresos', 'ventas', 'traslados'] as $tipo) {
            $this->getJson('/api/'.$tipo.'/000000000000000000000000')->assertNotFound();
            $this->getJson('/api/'.$tipo.'/incorrecto')->assertNotFound();
        }
        $this->withToken('invalido');
        $this->getJson('/api/ingresos')->assertUnauthorized();
        $empleado = Usuario::factory()->create(['modulos' => ['ventas']]);
        $this->entrar($empleado);
        $this->getJson('/api/catalogos/productos')->assertOk()->assertJsonMissingPath('0.precio_costo');
        $this->getJson('/api/catalogos/clientes')->assertOk();
        $this->getJson('/api/traslados')->assertForbidden();
    }

    public function test_dos_ventas_simultaneas_no_dejan_stock_negativo(): void
    {
        $this->ingresar();
        $resultados = $this->paralelo([
            ['accion' => 'crear', 'tipo' => 'ventas', 'datos' => $this->datos('ventas', 4)],
            ['accion' => 'crear', 'tipo' => 'ventas', 'datos' => $this->datos('ventas', 4)],
        ]);
        $this->assertSame([201, 409], $resultados);
        $this->assertSame(1, $this->stock());
        $this->assertSame(1, Venta::count());
        $this->assertSame(2, Movimiento::count());
    }

    public function test_confirmacion_y_anulacion_simultaneas_no_duplican_existencias(): void
    {
        $this->ingresar();
        $id = $this->postJson('/api/traslados', $this->datos('traslados', 4))->assertCreated()->json('_id');
        $resultados = $this->paralelo([
            ['accion' => 'confirmar', 'tipo' => 'traslados', 'id' => $id, 'sucursal_activa' => (string) $this->destino->getKey()],
            ['accion' => 'anular', 'tipo' => 'traslados', 'id' => $id],
        ]);
        $this->assertSame([200, 409], $resultados);
        $this->assertSame(5, $this->stock() + $this->stock((string) $this->destino->getKey()));
        $this->assertSame(3, Movimiento::count());
    }

    public function test_mismo_ingreso_simultaneo_solo_suma_una_vez(): void
    {
        $datos = $this->datos('ingresos', 5);
        $resultados = $this->paralelo([
            ['accion' => 'crear', 'tipo' => 'ingresos', 'datos' => $datos],
            ['accion' => 'crear', 'tipo' => 'ingresos', 'datos' => $datos],
        ]);
        $this->assertSame([201, 201], $resultados);
        $this->assertSame(5, $this->stock());
        $this->assertSame(1, Ingreso::count());
        $this->assertSame(1, Movimiento::count());
    }

    public function test_inventario_sin_indices_preparados_no_acepta_escrituras(): void
    {
        DB::connection('mongodb')->table('migrations')->delete();
        $this->postJson('/api/ingresos', $this->datos('ingresos'))->assertStatus(503);
        $this->assertSame(0, Ingreso::count());
        $this->assertSame(0, Existencia::count());
    }

    public function test_servicio_rechaza_movimientos_fuera_de_una_transaccion(): void
    {
        $this->expectException(\LogicException::class);
        $this->app->make(InventarioService::class)->mover([], $this->admin->sucursal_id, 1, []);
    }

    public function test_limite_de_pin_es_por_usuario_y_no_bloquea_a_otro_cajero(): void
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson('/api/auth/verificar-codigo', ['codigo' => '0000'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/verificar-codigo', ['codigo' => '4321'])->assertTooManyRequests();
        $otro = Usuario::factory()->create(['rol' => 'empleado', 'modulos' => ['ventas'], 'codigo_hash' => Hash::make('4321')]);
        $this->entrar($otro);
        $this->postJson('/api/auth/verificar-codigo', ['codigo' => '4321'])->assertOk();
    }

    private function paralelo(array $acciones): array
    {
        $procesos = [];
        $inicio = microtime(true) + 1.5;
        foreach ($acciones as $accion) {
            $datos = array_merge($accion, ['base' => config('database.connections.mongodb.database'), 'usuario_id' => (string) $this->admin->getKey(), 'inicio' => $inicio]);
            $proceso = new Process([PHP_BINARY, base_path('tests/Fixtures/operacion_paralela.php'), base64_encode(json_encode($datos))], base_path(), ['APP_ENV' => 'testing']);
            $proceso->setTimeout(25);
            $proceso->start();
            $procesos[] = $proceso;
        }
        $estados = [];
        foreach ($procesos as $proceso) {
            $proceso->wait();
            $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput().$proceso->getOutput());
            $estados[] = json_decode($proceso->getOutput(), true, flags: JSON_THROW_ON_ERROR)['estado'];
        }
        sort($estados);

        return $estados;
    }
}
