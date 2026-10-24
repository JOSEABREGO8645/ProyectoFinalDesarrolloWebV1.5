<?php

namespace Tests\Feature\Catalogos;

use App\Models\Existencia;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CatalogoTestCase;

class ProductosTest extends CatalogoTestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge(['codigo' => 'ABC-123', 'nombre' => 'Cuaderno ficticio', 'precio_costo' => '5.50', 'precio_venta' => '10.25'], $cambios);
    }

    public function test_crud_con_proveedor_y_precios_decimales(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Proveedor de prueba']);
        $respuesta = $this->postJson('/api/productos', $this->datos(['proveedor_id' => (string) $proveedor->getKey(), 'existencia' => 999]))
            ->assertCreated()->assertJsonPath('datos.precio_venta', '10.25')->assertJsonPath('datos.activo', true);
        $id = $respuesta->json('datos._id');
        $this->assertSame((string) $proveedor->getKey(), Producto::findOrFail($id)->proveedor_id);
        $this->assertNull(Producto::findOrFail($id)->existencia);
        $this->assertSame(1, DB::connection('mongodb')->table('productos')->where('codigo', 'ABC-123')->count());
        $this->getJson('/api/productos')->assertOk()->assertJsonCount(1)->assertJsonPath('0.proveedor_nombre', 'Proveedor de prueba')->assertJsonPath('0.existencia', 0);
        $this->getJson('/api/productos/'.$id)->assertOk()->assertJsonPath('_id', $id);
        $this->putJson('/api/productos/'.$id, $this->datos(['nombre' => 'Producto editado', 'precio_venta' => '12.50', 'proveedor_id' => null]))
            ->assertOk()->assertJsonPath('datos.precio_venta', '12.50')->assertJsonPath('datos.proveedor_id', null);
        $this->assertSame('Producto editado', Producto::findOrFail($id)->nombre);
        $this->deleteJson('/api/productos/'.$id)->assertOk();
        $this->assertNull(Producto::find($id));
    }

    public function test_codigo_unico_es_literal_y_no_distingue_mayusculas(): void
    {
        Producto::factory()->create(['codigo' => 'COD_%']);
        $this->postJson('/api/productos', $this->datos(['codigo' => 'cod_%']))->assertUnprocessable()->assertJsonPath('message', 'Ya existe un producto con ese código.');
        $this->postJson('/api/productos', $this->datos(['codigo' => 'COD_1']))->assertCreated();
        $this->assertSame(2, Producto::count());
    }

    public function test_proveedor_inexistente_no_guarda_ni_modifica_producto(): void
    {
        $datos = $this->datos(['proveedor_id' => '000000000000000000000000']);
        $this->postJson('/api/productos', $datos)->assertUnprocessable()->assertJsonPath('message', 'El proveedor seleccionado no existe.');
        $producto = Producto::factory()->create(['codigo' => 'ABC-123']);
        $this->putJson('/api/productos/'.$producto->getKey(), $datos)->assertUnprocessable();
        $this->assertNull($producto->fresh()->proveedor_id);
        $this->assertSame(1, Producto::count());
    }

    #[DataProvider('datosInvalidos')]
    public function test_campos_invalidos_devuelven_422(array $cambios, string $campo): void
    {
        $this->postJson('/api/productos', $this->datos($cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertSame(0, Producto::count());
    }

    public static function datosInvalidos(): array
    {
        return [
            'sin código' => [['codigo' => ''], 'codigo'],
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'precio negativo' => [['precio_costo' => -1], 'precio_costo'],
            'tres decimales' => [['precio_venta' => '1.235'], 'precio_venta'],
            'precio excesivo' => [['precio_venta' => '100000000.00'], 'precio_venta'],
            'stock fraccionario' => [['stock_minimo' => 1.5], 'stock_minimo'],
            'proveedor mal formado' => [['proveedor_id' => 'invalido'], 'proveedor_id'],
        ];
    }

    public function test_filtros_combinados_orden_numerico_y_existencias_por_sucursal(): void
    {
        $proveedor = Proveedor::factory()->create();
        $principal = Sucursal::create(['nombre' => 'Sucursal principal']);
        $otra = Sucursal::create(['nombre' => 'Sucursal secundaria']);
        $barato = Producto::factory()->create(['nombre' => 'Cuaderno barato', 'categoria' => 'Papelería', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_venta' => '2.50']);
        Producto::factory()->create(['nombre' => 'Cuaderno caro', 'categoria' => 'Papelería', 'proveedor_id' => (string) $proveedor->getKey(), 'precio_venta' => '10.00']);
        Producto::factory()->create(['nombre' => 'Inactivo', 'activo' => false]);
        Existencia::create(['producto_id' => (string) $barato->getKey(), 'sucursal_id' => (string) $principal->getKey(), 'cantidad' => 3]);
        Existencia::create(['producto_id' => (string) $barato->getKey(), 'sucursal_id' => (string) $otra->getKey(), 'cantidad' => 7]);
        $consulta = http_build_query(['q' => 'Cuaderno', 'categoria' => 'Papelería', 'proveedor_id' => (string) $proveedor->getKey(), 'activo' => '1', 'orden' => 'precio_venta']);
        $this->getJson('/api/productos/buscar?'.$consulta)->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.nombre', 'Cuaderno barato')->assertJsonPath('0.existencia', 10)->assertJsonPath('1.existencia', 0);
        $this->getJson('/api/productos/buscar?'.$consulta.'&sucursal_id='.$principal->getKey())
            ->assertOk()->assertJsonPath('0.existencia', 3);
        $this->getJson('/api/productos/buscar?'.$consulta.'&dir=desc')->assertOk()->assertJsonPath('0.nombre', 'Cuaderno caro');
        $this->getJson('/api/productos/buscar?activo=0')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Inactivo');
        $this->getJson('/api/productos/buscar?sucursal_id=000000000000000000000000')->assertNotFound();
        $this->getJson('/api/productos/buscar?orden=password')->assertUnprocessable()->assertJsonValidationErrors('orden');
    }

    #[DataProvider('referencias')]
    public function test_producto_con_historial_o_existencias_no_se_elimina(string $coleccion, string $campo): void
    {
        $producto = Producto::factory()->create();
        $referencia = $campo === 'producto_id' ? ['producto_id' => (string) $producto->getKey()] : ['items' => [['producto_id' => (string) $producto->getKey(), 'cantidad' => 1]]];
        DB::connection('mongodb')->table($coleccion)->insert([$referencia]);
        $this->deleteJson('/api/productos/'.$producto->getKey())->assertConflict();
        $this->assertNotNull($producto->fresh());
    }

    public static function referencias(): array
    {
        return [
            ['existencias', 'producto_id'], ['movimientos', 'producto_id'],
            ['ventas', 'items.producto_id'], ['traslados', 'items.producto_id'], ['ingresos', 'items.producto_id'],
        ];
    }

    public function test_id_inexistente_o_invalido_devuelve_404(): void
    {
        foreach (['000000000000000000000000', 'invalido'] as $id) {
            $this->getJson('/api/productos/'.$id)->assertNotFound();
            $this->putJson('/api/productos/'.$id, $this->datos())->assertNotFound();
            $this->deleteJson('/api/productos/'.$id)->assertNotFound();
        }
    }
}
