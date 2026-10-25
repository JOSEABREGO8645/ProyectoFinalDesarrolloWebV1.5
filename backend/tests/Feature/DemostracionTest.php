<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Traslado;
use App\Models\Usuario;
use App\Models\Venta;
use App\Services\OperacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\MongoTestCase;

class DemostracionTest extends MongoTestCase
{
    private string $baseDemo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDemo = 'libreria_demo_test_'.bin2hex(random_bytes(8));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->baseDemo) && preg_match('/^libreria_demo_test_[a-f0-9]{16}$/D', $this->baseDemo)) {
                $this->enDemo(fn () => DB::connection('mongodb')->getMongoDB()->drop());
            }
        } finally {
            parent::tearDown();
        }
    }

    private function enDemo(callable $accion): mixed
    {
        $original = config('database.connections.mongodb.database');
        config(['database.connections.mongodb.database' => $this->baseDemo]);
        DB::purge('mongodb');
        try {
            return $accion();
        } finally {
            DB::purge('mongodb');
            config(['database.connections.mongodb.database' => $original]);
        }
    }

    private function preparar(): void
    {
        $this->artisan('libreria:demo', ['--base' => $this->baseDemo])->assertExitCode(0)->run();
    }

    private function accesos(): array
    {
        return json_decode(file_get_contents(storage_path('app/private/demo/'.$this->baseDemo.'/accesos.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_carga_completa_separada_y_repeticion_conservan_datos_credenciales_y_kardex(): void
    {
        Sucursal::create(['nombre' => 'Datos originales de prueba', 'activo' => true]);
        $original = config('database.connections.mongodb.database');
        $this->preparar();
        $this->assertSame($original, config('database.connections.mongodb.database'));
        $this->assertSame(1, Sucursal::count());
        $accesos = $this->accesos();
        $ids = $this->enDemo(function () {
            foreach ([Sucursal::class => 5, Proveedor::class => 5, Cliente::class => 5, Producto::class => 10, Usuario::class => 5, Ingreso::class => 6, Venta::class => 6, Traslado::class => 3, Movimiento::class => 29] as $modelo => $cantidad) {
                $this->assertSame($cantidad, $modelo::count());
            }
            $this->assertSame(123, (int) Existencia::sum('cantidad'));
            $this->assertSame(1, Traslado::where('estado', 'enviado')->count());
            $this->assertSame(1, Traslado::where('estado', 'confirmado')->count());
            $this->assertSame(1, Traslado::where('estado', 'anulado')->count());
            $saldos = [];
            foreach (Movimiento::orderBy('created_at')->orderBy('_id')->get() as $m) {
                $this->assertIsString($m->producto_id);
                $this->assertIsString($m->sucursal_id);
                $clave = $m->sucursal_id.':'.$m->producto_id;
                $this->assertSame($saldos[$clave] ?? 0, $m->antes);
                $this->assertSame($m->antes + $m->cantidad, $m->despues);
                $saldos[$clave] = $m->despues;
            }
            foreach (Existencia::all() as $e) {
                $this->assertSame($saldos[$e->sucursal_id.':'.$e->producto_id], $e->cantidad);
            }
            Producto::firstOrFail()->update(['nombre' => 'Cambio manual conservado']);

            return Usuario::pluck('_id')->map(fn ($id) => (string) $id)->all();
        });
        $this->preparar();
        $this->assertSame($accesos, $this->accesos());
        $this->enDemo(function () use ($ids) {
            $this->assertSame($ids, Usuario::pluck('_id')->map(fn ($id) => (string) $id)->all());
            $this->assertSame(1, Producto::where('nombre', 'Cambio manual conservado')->count());
            $this->assertSame(29, Movimiento::count());
            $this->assertSame(123, (int) Existencia::sum('cantidad'));
        });
        Http::assertNothingSent();
    }

    public function test_carga_interrumpida_se_reanuda_sin_repetir_operaciones(): void
    {
        $real = app(OperacionService::class);
        $llamadas = 0;
        $this->mock(OperacionService::class)->shouldReceive('crear')->andReturnUsing(function (...$args) use ($real, &$llamadas) {
            if (++$llamadas === 3) {
                throw new \RuntimeException('Interrupción controlada');
            }

            return $real->crear(...$args);
        });
        try {
            $this->preparar();
            $this->fail('La interrupción debía propagarse');
        } catch (\RuntimeException $e) {
            $this->assertSame('Interrupción controlada', $e->getMessage());
        }
        $this->enDemo(function () {
            $this->assertSame(1, Ingreso::count());
            $this->assertSame(1, Venta::count());
            $this->assertSame(4, Movimiento::count());
        });
        $this->app->forgetInstance(OperacionService::class);
        $this->preparar();
        $this->enDemo(function () {
            $this->assertSame(29, Movimiento::count());
            $this->assertSame(123, (int) Existencia::sum('cantidad'));
        });
    }

    public function test_rechaza_nombres_ajenos_base_actual_y_produccion(): void
    {
        foreach (['libreria_san_francisco', '../libreria_demo', 'libreria_demo_otro', config('database.connections.mongodb.database')] as $base) {
            $this->artisan('libreria:demo', ['--base' => $base])->assertExitCode(1)->run();
        }
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('libreria:demo', ['--base' => $this->baseDemo])->assertExitCode(1)->run();
        $this->assertSame(0, Sucursal::count());
    }

    public function test_rechaza_base_ocupada_sin_marca(): void
    {
        $this->enDemo(fn () => Sucursal::create(['nombre' => 'No sobrescribir', 'activo' => true]));
        $this->artisan('libreria:demo', ['--base' => $this->baseDemo])->assertExitCode(1)->run();
        $this->enDemo(function () {
            $this->assertSame(1, Sucursal::count());
            $this->assertSame(0, Producto::count());
        });
    }

    public function test_datos_habilitan_dashboard_y_los_nueve_reportes_y_perfiles_limitados(): void
    {
        $this->preparar();
        $cuentas = $this->accesos()['cuentas'];
        $this->enDemo(function () use ($cuentas) {
            $this->withHeader('X-Requested-With', 'XMLHttpRequest');
            $login = $this->postJson('/api/auth/login', ['usuario' => $cuentas[0]['usuario'], 'password' => $cuentas[0]['password']])->assertOk();
            $this->withToken($login->json('access_token'));
            $this->getJson('/api/dashboard')->assertOk();
            foreach (array_keys(config('reportes')) as $tipo) {
                $r = $this->getJson('/api/reportes/'.$tipo)->assertOk();
                $this->assertNotEmpty($r->json('filas'), $tipo);
            }
            $login = $this->postJson('/api/auth/login', ['usuario' => $cuentas[3]['usuario'], 'password' => $cuentas[3]['password']])->assertOk();
            $this->withToken($login->json('access_token'));
            $this->getJson('/api/reportes')->assertOk();
            $this->getJson('/api/productos')->assertForbidden();
            $this->getJson('/api/reportes/ventas-diarias?sucursal_id='.Sucursal::where('nombre', 'DEMO - Norte')->firstOrFail()->getKey())->assertForbidden();
        });
    }

    public function test_credenciales_modificadas_o_malformadas_no_reemplazan_las_cuentas(): void
    {
        $this->preparar();
        $original = $this->accesos();
        $ruta = storage_path('app/private/demo/'.$this->baseDemo.'/accesos.json');
        foreach ([['jwt_secret' => []], ['cuentas' => 'invalido'], ['cuentas' => [null, null, null, null, null]], ['jwt_secret' => str_repeat('f', 64)]] as $cambio) {
            $contenido = json_encode(array_merge($original, $cambio), JSON_THROW_ON_ERROR);
            file_put_contents($ruta, $contenido);
            $this->artisan('libreria:demo', ['--base' => $this->baseDemo])->assertExitCode(1)->run();
            $this->assertSame($contenido, file_get_contents($ruta));
        }
        $this->enDemo(function () use ($original) {
            $this->assertSame(5, Usuario::count());
            $this->assertTrue(Hash::check($original['cuentas'][0]['password'], Usuario::where('usuario', 'admin.demo')->firstOrFail()->password));
            $this->assertSame(29, Movimiento::count());
        });
    }

    public function test_reserva_activa_rechaza_otra_carga_y_reserva_vencida_permite_reanudar(): void
    {
        $this->preparar();
        $this->enDemo(fn () => DB::connection('mongodb')->table('contadores')->where('_id', 'demostracion:v1')->update(['estado' => 'cargando', 'vence' => now()->timestamp + 600]));
        $this->artisan('libreria:demo', ['--base' => $this->baseDemo])->assertExitCode(1)->run();
        $this->enDemo(fn () => DB::connection('mongodb')->table('contadores')->where('_id', 'demostracion:v1')->update(['vence' => 0]));
        $this->preparar();
        $this->enDemo(function () {
            $this->assertSame(29, Movimiento::count());
            $this->assertSame(123, (int) Existencia::sum('cantidad'));
        });
    }
}
