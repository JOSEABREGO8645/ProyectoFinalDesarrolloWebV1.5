<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Existencia;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\CatalogoTestCase;

class CajaVentaTest extends CatalogoTestCase
{
    private function producto(): Producto
    {
        $this->artisan('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php'])->assertExitCode(0)->run();
        $admin = Usuario::findOrFail($this->getJson('/api/auth/me')->json('_id'));
        $admin->update(['codigo_hash' => Hash::make('4321')]);
        $proveedor = Proveedor::factory()->create(['activo' => true]);
        $p = Producto::factory()->create(['codigo' => '001234', 'activo' => true, 'proveedor_id' => (string) $proveedor->getKey(), 'precio_costo' => '0.47', 'precio_venta' => '1.01']);
        $this->postJson('/api/ingresos', ['operacion_id' => (string) Str::uuid(), 'proveedor_id' => (string) $proveedor->getKey(), 'motivo' => 'Existencias para caja', 'items' => [['producto_id' => (string) $p->getKey(), 'cantidad' => 10]]])->assertCreated();

        return $p;
    }

    public function test_codigo_exacto_no_confunde_nombre_prefijo_ni_ceros_y_escapa_regex(): void
    {
        Producto::factory()->create(['codigo' => '001234', 'nombre' => 'Último producto', 'activo' => true]);
        Producto::factory()->create(['codigo' => '1234', 'nombre' => '001234', 'activo' => true]);
        Producto::factory()->create(['codigo' => '0012345', 'activo' => true]);
        Producto::factory()->create(['codigo' => '00[12]', 'activo' => true]);
        Producto::factory()->create(['codigo' => '001', 'activo' => true]);
        Producto::factory()->create(['codigo' => 'INACTIVO', 'activo' => false]);
        $this->getJson('/api/catalogos/productos?codigo=001234')->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', '001234');
        $this->getJson('/api/catalogos/productos?codigo=00%5B12%5D')->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', '00[12]');
        $this->getJson('/api/catalogos/productos?codigo=INACTIVO')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/catalogos/productos?codigo=NO-EXISTE')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/catalogos/productos?codigo='.str_repeat('x', 41))->assertUnprocessable();
    }

    public function test_codigo_exacto_se_filtra_antes_del_limite_de_catalogo(): void
    {
        Producto::factory()->count(501)->create(['nombre' => 'Antes del objetivo', 'activo' => true]);
        Producto::factory()->create(['codigo' => '000999', 'nombre' => 'ZZZ último', 'activo' => true]);
        $this->getJson('/api/catalogos/productos?codigo=000999')->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', '000999');
    }

    public function test_no_factura_si_el_total_revisado_cambio_y_reintento_no_duplica(): void
    {
        $p = $this->producto();
        $venta = ['operacion_id' => (string) Str::uuid(), 'codigo' => '4321', 'efectivo' => '10.00', 'total_esperado_centavos' => 300, 'items' => [['producto_id' => (string) $p->getKey(), 'cantidad' => 3]]];
        $this->postJson('/api/ventas', $venta)->assertConflict();
        $this->assertSame(0, Venta::count());
        $this->assertSame(1, Movimiento::count());
        $this->assertSame(10, Existencia::firstOrFail()->cantidad);
        $venta['total_esperado_centavos'] = 303;
        $id = $this->postJson('/api/ventas', $venta)->assertCreated()->assertJsonPath('total_centavos', 303)->json('_id');
        $p->update(['precio_venta' => '2.01']);
        $this->postJson('/api/ventas', $venta)->assertCreated()->assertJsonPath('_id', $id)->assertJsonPath('total_centavos', 303);
        $this->getJson('/api/ventas?operacion_id='.$venta['operacion_id'])->assertOk()->assertJsonCount(1)->assertJsonPath('0._id', $id);
        $this->getJson('/api/ventas?operacion_id='.(string) Str::uuid())->assertOk()->assertJsonCount(0);
        $this->assertSame(1, Venta::count());
        $this->assertSame(2, Movimiento::count());
        $this->assertSame(7, Existencia::firstOrFail()->cantidad);
    }

    public function test_total_esperado_invalido_no_genera_documentos(): void
    {
        $p = $this->producto();
        foreach ([-1, 1.5, 10000000000] as $total) {
            $this->postJson('/api/ventas', ['operacion_id' => (string) Str::uuid(), 'codigo' => '4321', 'efectivo' => '10.00', 'total_esperado_centavos' => $total, 'items' => [['producto_id' => (string) $p->getKey(), 'cantidad' => 1]]])->assertUnprocessable();
        }
        $this->assertSame(0, Venta::count());
    }

    public function test_nit_exacto_normalizado_y_cajero_no_recibe_costos_ni_permiso_de_crear_clientes(): void
    {
        Cliente::factory()->create(['nit' => '12345', 'nombre' => 'Cliente exacto']);
        Cliente::factory()->create(['nit' => '123456', 'nombre' => 'Otro cliente']);
        $usuario = Usuario::factory()->create(['rol' => 'empleado', 'modulos' => ['ventas']]);
        $login = $this->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'])->assertOk();
        $this->withToken($login->json('access_token'));
        Producto::factory()->create(['codigo' => '001234', 'activo' => true]);
        $this->getJson('/api/catalogos/clientes?nit=123-45')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Cliente exacto');
        $this->getJson('/api/catalogos/productos?codigo=001234')->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.precio_costo');
        $this->postJson('/api/clientes', ['nombre' => 'Sin permiso', 'nit' => '555'])->assertForbidden();
        $this->getJson('/api/ventas/ffffffffffffffffffffffff')->assertNotFound();
        $usuario->update(['modulos' => []]);
        $this->getJson('/api/catalogos/productos?codigo=001234')->assertForbidden();
        $this->flushHeaders();
        $this->getJson('/api/catalogos/clientes?nit=12345')->assertUnauthorized();
    }
}
