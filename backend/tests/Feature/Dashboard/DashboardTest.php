<?php

namespace Tests\Feature\Dashboard;

use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Traslado;
use App\Models\Usuario;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CatalogoTestCase;

class DashboardTest extends CatalogoTestCase
{
    private function sede(): string
    {
        return $this->getJson('/api/auth/me')->json('sucursal_id');
    }

    private function entrar(Usuario $usuario): void
    {
        $r = $this->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($r->json('access_token'));
    }

    private function venta(string $sede, int $centavos, string $fecha = '2026-10-24T12:00:00Z', string $estado = 'emitida'): Venta
    {
        return Venta::create(['sucursal_id' => $sede, 'sucursal_nombre' => 'Sede ficticia', 'numero' => random_int(1, 1000000),
            'estado' => $estado, 'total_centavos' => $centavos, 'items' => [['cantidad' => 2]], 'created_at' => CarbonImmutable::parse($fecha)]);
    }

    public function test_base_vacia_devuelve_ceros_y_dias_sin_inventar_datos(): void
    {
        $this->getJson('/api/dashboard?desde=2026-10-23&hasta=2026-10-24')->assertOk()
            ->assertJsonPath('inventario.unidades', 0)->assertJsonPath('inventario.activos', 0)
            ->assertJsonPath('ventas.total_centavos', 0)->assertJsonPath('ingresos.documentos', 0)
            ->assertJsonPath('traslados.pendientes.documentos', 0)->assertJsonCount(2, 'ventas.diarias')
            ->assertJsonPath('ventas.diarias.0.fecha', '2026-10-23')->assertJsonPath('periodo.zona', 'America/Guatemala');
    }

    public function test_existencias_por_sucursal_y_totales_incluyen_inactivos_sin_alertarlos(): void
    {
        $sede = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Sucursal de prueba', 'activo' => true])->getKey();
        Producto::factory()->create(['activo' => true, 'stock_minimo' => 5]);
        $bajo = Producto::factory()->create(['activo' => true, 'stock_minimo' => 5]);
        $inactivo = Producto::factory()->create(['activo' => false, 'stock_minimo' => 20]);
        foreach ([[$bajo, $sede, 3], [$bajo, $otra, 7], [$inactivo, $sede, 10]] as [$p,$s,$q]) {
            Existencia::create(['producto_id' => (string) $p->getKey(), 'sucursal_id' => $s, 'cantidad' => $q]);
        }
        $this->getJson('/api/dashboard?sucursal_id='.$sede)->assertOk()->assertJsonPath('inventario.unidades', 13)
            ->assertJsonPath('inventario.activos', 2)->assertJsonPath('inventario.inactivos', 1)
            ->assertJsonPath('inventario.agotados', 1)->assertJsonPath('inventario.bajos', 1)->assertJsonCount(2, 'inventario.alertas');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('inventario.unidades', 20)->assertJsonPath('inventario.bajos', 0);
    }

    public function test_periodo_respeta_medianoche_de_guatemala_y_excluye_anulados(): void
    {
        $sede = $this->sede();
        $this->venta($sede, 101, '2026-10-24T06:00:00Z');
        $this->venta($sede, 202, '2026-10-25T05:59:59Z');
        $this->venta($sede, 9000, '2026-10-24T05:59:59Z');
        $this->venta($sede, 8000, '2026-10-25T06:00:00Z');
        $this->venta($sede, 400, '2026-10-24T12:00:00Z', 'anulado');
        $this->getJson('/api/dashboard?desde=2026-10-24&hasta=2026-10-24')->assertOk()
            ->assertJsonPath('ventas.total_centavos', 303)->assertJsonPath('ventas.documentos', 2)
            ->assertJsonPath('ventas.unidades', 4)->assertJsonPath('ventas.anulados', 1)
            ->assertJsonPath('ventas.diarias.0.centavos', 303)->assertJsonCount(3, 'ventas.recientes');
    }

    public function test_resumen_no_se_limita_a_los_cinco_documentos_recientes(): void
    {
        $sede = $this->sede();
        for ($i = 0; $i < 7; $i++) {
            $this->venta($sede, 100, '2026-10-24T12:0'.$i.':00Z');
        }
        Ingreso::create(['sucursal_id' => $sede, 'estado' => 'registrado', 'total_centavos' => 470, 'items' => [['cantidad' => 10]], 'created_at' => CarbonImmutable::parse('2026-10-24T15:00:00Z')]);
        Ingreso::create(['sucursal_id' => $sede, 'estado' => 'anulado', 'total_centavos' => 900, 'items' => [['cantidad' => 1]], 'created_at' => CarbonImmutable::parse('2026-10-24T15:00:00Z')]);
        $this->getJson('/api/dashboard?desde=2026-10-23&hasta=2026-10-24')->assertOk()
            ->assertJsonPath('ventas.total_centavos', 700)->assertJsonPath('ventas.documentos', 7)->assertJsonCount(5, 'ventas.recientes')
            ->assertJsonPath('ventas.diarias.0.centavos', 0)->assertJsonPath('ventas.diarias.1.centavos', 700)
            ->assertJsonPath('ingresos.total_centavos', 470)->assertJsonPath('ingresos.unidades', 10)->assertJsonPath('ingresos.anulados', 1);
    }

    public function test_empleado_solo_ve_su_sucursal_y_modulos_permitidos(): void
    {
        $sede = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Sucursal de prueba', 'activo' => true])->getKey();
        $propia = $this->venta($sede, 303);
        $this->venta($otra, 9999);
        $usuario = Usuario::factory()->create(['sucursal_id' => $sede, 'modulos' => ['ventas']]);
        $this->entrar($usuario);
        $this->getJson('/api/dashboard?desde=2026-10-24&hasta=2026-10-24')->assertOk()->assertJsonPath('sucursal_id', $sede)
            ->assertJsonPath('ventas.total_centavos', 303)->assertJsonPath('ventas.recientes.0._id', (string) $propia->getKey())
            ->assertJsonMissingPath('inventario')->assertJsonMissingPath('ingresos')->assertJsonMissingPath('traslados');
        $this->getJson('/api/dashboard?sucursal_id='.$otra)->assertForbidden();
        $this->getJson('/api/catalogos/sucursales')->assertOk()->assertJsonCount(1);
    }

    public function test_pendientes_son_actuales_aunque_el_envio_sea_anterior_al_periodo(): void
    {
        $sede = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Sucursal de prueba', 'activo' => true])->getKey();
        $tercera = (string) Sucursal::create(['nombre' => 'Sucursal de prueba', 'activo' => true])->getKey();
        foreach ([[$sede, $otra, 'enviado', 3], [$otra, $sede, 'enviado', 4], [$otra, $tercera, 'enviado', 100], [$sede, $otra, 'anulado', 9]] as [$o,$d,$e,$q]) {
            Traslado::create(['sucursal_id' => $o, 'origen_id' => $o, 'destino_id' => $d, 'estado' => $e, 'items' => [['cantidad' => $q]], 'created_at' => CarbonImmutable::parse('2026-01-01')]);
        }
        $this->getJson('/api/dashboard?sucursal_id='.$sede.'&desde=2026-10-24&hasta=2026-10-24')->assertOk()
            ->assertJsonPath('traslados.documentos', 0)->assertJsonPath('traslados.pendientes.documentos', 2)->assertJsonPath('traslados.pendientes.unidades', 7);
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_rechaza_filtros_invalidos(string $query, string $campo): void
    {
        $this->getJson('/api/dashboard?'.$query)->assertUnprocessable()->assertJsonValidationErrors($campo);
    }

    public static function filtrosInvalidos(): array
    {
        return [['desde=2026-02-30&hasta=2026-03-01', 'desde'], ['desde=2026-10-25&hasta=2026-10-24', 'hasta'],
            ['desde=2026-10-24', 'hasta'], ['sucursal_id=incorrecta', 'sucursal_id'], ['desde[]=2026-10-24', 'desde']];
    }

    public function test_rechaza_rango_mayor_de_90_dias_y_sucursal_inexistente(): void
    {
        $this->getJson('/api/dashboard?desde=2026-01-01&hasta=2026-10-24')->assertUnprocessable()->assertJsonPath('message', 'Seleccione un período de hasta 90 días.');
        $this->getJson('/api/dashboard?sucursal_id=000000000000000000000000')->assertNotFound();
    }

    public function test_sin_sesion_o_sin_modulo_no_accede(): void
    {
        $this->withToken('invalido')->getJson('/api/dashboard')->assertUnauthorized();
        $usuario = Usuario::factory()->create(['modulos' => ['proveedores']]);
        $this->entrar($usuario);
        $this->getJson('/api/dashboard')->assertForbidden();
    }
}
