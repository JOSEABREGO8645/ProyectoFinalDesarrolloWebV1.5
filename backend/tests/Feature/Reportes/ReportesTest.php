<?php

namespace Tests\Feature\Reportes;

use App\Models\Existencia;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Traslado;
use App\Models\Usuario;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CatalogoTestCase;

class ReportesTest extends CatalogoTestCase
{
    private function sede(): string
    {
        return $this->getJson('/api/auth/me')->json('sucursal_id');
    }

    private function fecha(string $f = '2026-10-24T12:00:00Z'): CarbonImmutable
    {
        return CarbonImmutable::parse($f);
    }

    private function url(string $tipo, string $extra = ''): string
    {
        return '/api/reportes/'.$tipo.'?desde=2026-10-24&hasta=2026-10-24'.$extra;
    }

    private function resumen(array $resumen, string $label): mixed
    {
        foreach ($resumen as $r) {
            if ($r['label'] === $label) {
                return $r['valor'];
            }
        }

        return null;
    }

    public function test_nueve_reportes_distintos_y_csv_vacios_son_validos(): void
    {
        $lista = $this->getJson('/api/reportes')->assertOk()->assertJsonCount(9, 'reportes')->json('reportes');
        $this->assertCount(9, array_unique(array_column($lista, 'titulo')));
        foreach ($lista as $reporte) {
            $r = $this->getJson('/api/reportes/'.$reporte['id'])->assertOk()->assertJsonCount(0, 'filas');
            $this->assertSame(0, $r->json('paginacion.total'));
            $csv = $this->get('/api/reportes/'.$reporte['id'].'?formato=csv')->assertOk()->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertStringContainsString($reporte['columnas'][0]['label'], $csv);
        }
    }

    public function test_existencias_paginadas_con_totales_completos_y_filtro_literal_cero(): void
    {
        $s = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Otra', 'activo' => true])->getKey();
        $p = Producto::factory()->create(['codigo' => '000012-1', 'nombre' => 'Libro cero', 'categoria' => '0', 'activo' => true, 'stock_minimo' => 5]);
        Producto::factory()->create(['codigo' => 'ZZZ', 'nombre' => 'Otro producto', 'categoria' => 'Otra', 'activo' => true]);
        Existencia::create(['producto_id' => (string) $p->getKey(), 'sucursal_id' => $s, 'cantidad' => 5]);
        Existencia::create(['producto_id' => (string) $p->getKey(), 'sucursal_id' => $otra, 'cantidad' => 7]);
        $r = $this->getJson('/api/reportes/existencias?sucursal_id='.$s.'&por_pagina=1')->assertOk()->assertJsonCount(1, 'filas')->assertJsonPath('paginacion.total', 2);
        $this->assertSame(5, $this->resumen($r->json('resumen'), 'Unidades'));
        $this->getJson('/api/reportes/existencias?sucursal_id='.$s.'&q=0&categoria=0')->assertOk()->assertJsonCount(1, 'filas')->assertJsonPath('filas.0.codigo', '000012-1')->assertJsonPath('filas.0.estado', 'Bajo');
        $this->getJson('/api/reportes/existencias?q=Libro.*')->assertOk()->assertJsonCount(0, 'filas');
        $this->getJson('/api/reportes/existencias?producto_id='.$p->getKey())->assertOk()->assertJsonPath('filas.0.cantidad', 12);
    }

    public function test_valoracion_por_categoria_conserva_centavos_incluso_en_importes_grandes(): void
    {
        $s = $this->sede();
        foreach ([['Pequeña', '0.47', '1.01', 3], ['Grande', '99999999.99', '99999999.99', 100000000]] as [$cat,$costo,$venta,$cantidad]) {
            $p = Producto::factory()->create(['categoria' => $cat, 'precio_costo' => $costo, 'precio_venta' => $venta]);
            Existencia::create(['producto_id' => (string) $p->getKey(), 'sucursal_id' => $s, 'cantidad' => $cantidad]);
        }
        $r = $this->getJson('/api/reportes/valoracion')->assertOk()->assertJsonCount(2, 'filas');
        $filas = array_column($r->json('filas'), null, 'categoria');
        $this->assertSame('1.41', $filas['Pequeña']['costo']);
        $this->assertSame('1.62', $filas['Pequeña']['margen']);
        $this->assertSame('9999999999000000.00', $filas['Grande']['costo']);
        $this->assertSame('9999999999000001.41', $this->resumen($r->json('resumen'), 'Valor al costo'));
    }

    public function test_ventas_diarias_ranking_y_clientes_excluyen_anuladas_y_respetan_guatemala(): void
    {
        $s = $this->sede();
        $pid = (string) Producto::factory()->create()->getKey();
        foreach ([['2026-10-24T06:00:00Z', 303, 3, 'emitida', 'Cliente A', '123', 'ABC', $pid], ['2026-10-25T05:59:59Z', 202, 2, 'emitida', 'Consumidor Final', 'CF', 'XYZ', '000000000000000000000001'], ['2026-10-24T12:00:00Z', 909, 9, 'anulado', 'Cliente A', '123', 'ABC', $pid], ['2026-10-24T05:59:59Z', 800, 8, 'emitida', 'Cliente A', '123', 'ABC', $pid]] as [$fecha,$total,$cantidad,$estado,$cliente,$nit,$codigo,$producto]) {
            Venta::create(['sucursal_id' => $s, 'estado' => $estado, 'cliente_id' => $nit === 'CF' ? null : '000000000000000000000002', 'cliente_nombre' => $cliente, 'cliente_nit' => $nit, 'total_centavos' => $total,
                'items' => [['producto_id' => $producto, 'codigo' => $codigo, 'nombre' => $codigo, 'cantidad' => $cantidad, 'subtotal_centavos' => $total]], 'created_at' => $this->fecha($fecha)]);
        }
        $this->getJson($this->url('ventas-diarias'))->assertOk()->assertJsonPath('filas.0.dia', '2026-10-24')->assertJsonPath('filas.0.total', '5.05')->assertJsonPath('filas.0.promedio', '2.53')->assertJsonPath('filas.0.documentos', 2);
        $this->getJson($this->url('productos-vendidos'))->assertOk()->assertJsonCount(2, 'filas')->assertJsonPath('filas.0.codigo', 'ABC')->assertJsonPath('filas.0.cantidad', 3)->assertJsonPath('filas.0.total', '3.03');
        $this->getJson($this->url('productos-vendidos', '&producto_id='.$pid))->assertOk()->assertJsonCount(1, 'filas');
        $this->getJson($this->url('ventas-clientes'))->assertOk()->assertJsonCount(2, 'filas')->assertJsonPath('filas.0.nombre', 'Cliente A')->assertJsonPath('filas.1.nit', 'CF');
    }

    public function test_ingresos_agrupados_por_proveedor_no_suman_anulados(): void
    {
        $s = $this->sede();
        $p = Proveedor::factory()->create();
        foreach ([['registrado', 470, 10], ['anulado', 900, 9]] as [$estado,$total,$cantidad]) {
            Ingreso::create(['sucursal_id' => $s, 'proveedor_id' => (string) $p->getKey(), 'proveedor_nombre' => $p->nombre, 'estado' => $estado, 'total_centavos' => $total, 'items' => [['cantidad' => $cantidad]], 'created_at' => $this->fecha()]);
        }
        $this->getJson($this->url('ingresos-proveedores', '&proveedor_id='.$p->getKey()))->assertOk()->assertJsonCount(1, 'filas')->assertJsonPath('filas.0.total', '4.70')->assertJsonPath('filas.0.documentos', 1)->assertJsonPath('filas.0.cantidad', 10);
    }

    public function test_flujo_agrupa_recorridos_y_estados_y_restringe_sucursal(): void
    {
        $s = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Otra', 'activo' => true])->getKey();
        foreach ([[$s, $otra, 'enviado', 1], [$s, $otra, 'enviado', 1], [$s, $otra, 'confirmado', 3], [$s, $otra, 'anulado', 1], [$otra, '000000000000000000000001', 'enviado', 100]] as [$o,$d,$e,$q]) {
            Traslado::create(['sucursal_id' => $o, 'origen_id' => $o, 'destino_id' => $d, 'origen_nombre' => 'Origen', 'destino_nombre' => 'Destino', 'estado' => $e, 'items' => [['cantidad' => $q]], 'created_at' => $this->fecha()]);
        }
        $r = $this->getJson($this->url('flujo-traslados', '&sucursal_id='.$s))->assertOk()->assertJsonCount(3, 'filas');
        $this->assertSame(4, $this->resumen($r->json('resumen'), 'Documentos'));
        $this->assertSame(3, $this->resumen($r->json('resumen'), 'Unidades confirmadas'));
        $this->assertSame(2, $this->resumen($r->json('resumen'), 'Unidades pendientes'));
        $this->getJson($this->url('flujo-traslados', '&sucursal_id='.$s.'&estado=confirmado'))->assertOk()->assertJsonCount(1, 'filas')->assertJsonPath('filas.0.cantidad', 3);
    }

    public function test_kardex_paginado_y_csv_exportan_todo_sin_repetir_movimientos(): void
    {
        $s = $this->sede();
        $p = Producto::factory()->create();
        foreach ([[5, 0, 5], [-2, 5, 3]] as [$q,$a,$d]) {
            Movimiento::create(['sucursal_id' => $s, 'producto_id' => (string) $p->getKey(), 'codigo' => '00001', 'nombre' => 'Libro', 'tipo' => $q > 0 ? 'ingreso' : 'venta', 'numero' => 1, 'cantidad' => $q, 'antes' => $a, 'despues' => $d, 'usuario_nombre' => 'Persona de pruebas', 'created_at' => $this->fecha()]);
        }
        $r = $this->getJson($this->url('kardex', '&por_pagina=1'))->assertOk()->assertJsonCount(1, 'filas')->assertJsonPath('paginacion.total', 2);
        $this->assertSame(5, $this->resumen($r->json('resumen'), 'Entradas del período'));
        $this->assertSame(2, $this->resumen($r->json('resumen'), 'Salidas del período'));
        $csv = $this->get($this->url('kardex', '&por_pagina=1&formato=csv'))->assertOk()->streamedContent();
        $lineas = explode("\r\n", trim($csv));
        $this->assertCount(3, $lineas);
        $this->assertStringContainsString('00001', $csv);
        $this->assertStringContainsString('-2', $csv);
    }

    public function test_auditoria_utiliza_fecha_anulacion_y_une_los_tres_procesos(): void
    {
        $s = $this->sede();
        foreach ([Venta::class, Ingreso::class, Traslado::class] as $modelo) {
            $modelo::create(['sucursal_id' => $s, 'origen_id' => $s, 'destino_id' => '000000000000000000000001', 'origen_nombre' => 'Origen', 'destino_nombre' => 'Destino', 'numero' => 1, 'estado' => 'anulado', 'total_centavos' => 909, 'anulado_at' => '2026-10-24T12:00:00+00:00', 'anulado_por' => 'Auditor', 'motivo_anulacion' => 'Prueba de reversión', 'created_at' => $this->fecha('2026-09-01')]);
        }
        $r = $this->getJson($this->url('anulaciones'))->assertOk()->assertJsonCount(3, 'filas');
        $this->assertSame(['ingresos', 'traslados', 'ventas'], array_column($r->json('filas'), 'documento'));
        $this->assertSame(1, $this->resumen($r->json('resumen'), 'Ventas anuladas'));
        $this->assertNull($r->json('filas.1.total'));
    }

    public function test_csv_neutraliza_formulas_en_textos_y_no_modifica_codigos_con_cero(): void
    {
        Producto::factory()->create(['codigo' => '000012-1', 'nombre' => '=SUM(1,1)']);
        $r = $this->get('/api/reportes/existencias?formato=csv')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $csv = $r->streamedContent();
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringContainsString('000012-1', $csv);
    }

    public function test_permiso_reportes_y_sucursal_se_exigen_en_json_y_csv(): void
    {
        $s = $this->sede();
        $otra = (string) Sucursal::create(['nombre' => 'Otra', 'activo' => true])->getKey();
        $u = Usuario::factory()->create(['modulos' => ['reportes'], 'sucursal_id' => $s]);
        $login = $this->postJson('/api/auth/login', ['usuario' => $u->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($login->json('access_token'));
        $this->getJson('/api/reportes')->assertOk();
        $this->getJson('/api/reportes/existencias')->assertOk()->assertJsonPath('filtros.sucursal_id', $s);
        foreach (['json', 'csv'] as $formato) {
            $this->getJson('/api/reportes/existencias?sucursal_id='.$otra.'&formato='.$formato)->assertForbidden();
        }
        $this->getJson('/api/catalogos/sucursales')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/catalogos/productos')->assertOk();
        $u2 = Usuario::factory()->create(['modulos' => ['ventas']]);
        $login = $this->postJson('/api/auth/login', ['usuario' => $u2->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($login->json('access_token'));
        $this->getJson('/api/reportes')->assertForbidden();
        $this->getJson('/api/reportes/existencias?formato=csv')->assertForbidden();
        $this->withToken('invalido')->getJson('/api/reportes')->assertUnauthorized();
    }

    #[DataProvider('invalidos')]
    public function test_filtros_invalidos_y_referencias_inexistentes(string $ruta, int $estado): void
    {
        $this->getJson('/api/reportes/'.$ruta)->assertStatus($estado);
    }

    public static function invalidos(): array
    {
        return [['desconocido', 404], ['existencias.columnas', 404], ['existencias?sucursal_id=000000000000000000000000', 404],
            ['existencias?producto_id=000000000000000000000000', 404], ['existencias?desde=2026-10-24&hasta=2026-10-24', 422],
            ['ventas-diarias?desde=2026-10-25&hasta=2026-10-24', 422], ['ventas-diarias?desde=2025-01-01&hasta=2026-10-24', 422],
            ['ventas-diarias?producto_id=000000000000000000000000', 422], ['existencias?por_pagina=101', 422], ['existencias?pagina=0', 422], ['existencias?formato=pdf', 422], ['existencias?proveedor_id[]=x', 422]];
    }
}
