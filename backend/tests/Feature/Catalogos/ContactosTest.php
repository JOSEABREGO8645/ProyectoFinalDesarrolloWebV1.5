<?php

namespace Tests\Feature\Catalogos;

use App\Models\Cliente;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CatalogoTestCase;

class ContactosTest extends CatalogoTestCase
{
    public function test_proveedor_se_crea_consulta_edita_y_elimina(): void
    {
        $respuesta = $this->postJson('/api/proveedores', [
            'nombre' => 'Papelería de prueba', 'contacto' => 'Ana Ficticia',
            'telefono' => '2222-1000', 'correo' => 'ana@example.com',
        ])->assertCreated()->assertJsonPath('datos.activo', true)->assertJsonStructure(['datos' => ['_id']]);
        $id = $respuesta->json('datos._id');
        $this->assertSame('Ana Ficticia', Proveedor::findOrFail($id)->contacto);
        $this->assertSame(1, DB::connection('mongodb')->table('proveedores')->where('nombre', 'Papelería de prueba')->count());
        $this->getJson('/api/proveedores')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/proveedores/'.$id)->assertOk()->assertJsonPath('nombre', 'Papelería de prueba');
        $this->putJson('/api/proveedores/'.$id, ['nombre' => 'Nombre actualizado', 'activo' => false])
            ->assertOk()->assertJsonPath('datos.activo', false);
        $this->assertSame('Nombre actualizado', Proveedor::findOrFail($id)->nombre);
        $this->deleteJson('/api/proveedores/'.$id)->assertOk();
        $this->assertNull(Proveedor::find($id));
    }

    public function test_cliente_normaliza_nit_y_conserva_datos_al_editar(): void
    {
        $respuesta = $this->postJson('/api/clientes', ['nombre' => 'Cliente ficticio', 'nit' => '1234-5'])
            ->assertCreated()->assertJsonPath('datos.nit', '12345')->assertJsonStructure(['datos' => ['_id']]);
        $id = $respuesta->json('datos._id');
        $this->assertSame('12345', Cliente::findOrFail($id)->nit);
        $this->assertSame(1, DB::connection('mongodb')->table('clientes')->where('nit', '12345')->count());
        $this->getJson('/api/clientes')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/clientes/'.$id)->assertOk()->assertJsonPath('nit', '12345');
        $this->getJson('/api/clientes/por-nit/1234-5')->assertOk()->assertJsonPath('_id', $id);
        $this->putJson('/api/clientes/'.$id, ['nombre' => 'Cliente actualizado', 'nit' => '1234-5'])
            ->assertOk()->assertJsonPath('datos.nombre', 'Cliente actualizado');
        $this->assertSame('Cliente actualizado', Cliente::findOrFail($id)->nombre);
        $this->deleteJson('/api/clientes/'.$id)->assertOk();
        $this->assertNull(Cliente::find($id));
    }

    public function test_nit_repetido_normalizado_devuelve_422_al_crear_o_editar(): void
    {
        Cliente::factory()->create(['nit' => 'CF']);
        $otro = Cliente::factory()->create(['nit' => '12345']);
        $this->postJson('/api/clientes', ['nombre' => 'Duplicado', 'nit' => ' c-f '])
            ->assertUnprocessable()->assertJsonPath('message', 'Ya existe un cliente con ese NIT.');
        $this->putJson('/api/clientes/'.$otro->getKey(), ['nombre' => 'Duplicado', 'nit' => 'cf'])
            ->assertUnprocessable();
        $this->assertSame('12345', $otro->fresh()->nit);
        $this->assertSame(2, Cliente::count());
    }

    public function test_nit_compuesto_solo_por_separadores_devuelve_422(): void
    {
        $this->postJson('/api/clientes', ['nombre' => 'Cliente ficticio', 'nit' => '- -'])
            ->assertUnprocessable()->assertJsonPath('message', 'El NIT es obligatorio; use CF para consumidor final.');
        $this->assertSame(0, Cliente::count());
    }

    #[DataProvider('entradasInvalidas')]
    public function test_datos_invalidos_devuelven_422(string $modulo, array $datos, string $campo): void
    {
        $this->postJson('/api/'.$modulo, $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    }

    public static function entradasInvalidas(): array
    {
        return [
            'nombre obligatorio' => ['proveedores', [], 'nombre'],
            'longitud nombre' => ['proveedores', ['nombre' => str_repeat('a', 151)], 'nombre'],
            'correo inválido' => ['proveedores', ['nombre' => 'Prueba', 'correo' => 'incorrecto'], 'correo'],
            'teléfono inválido' => ['clientes', ['nombre' => 'Prueba', 'nit' => 'CF', 'telefono' => 'abc'], 'telefono'],
            'nit obligatorio' => ['clientes', ['nombre' => 'Prueba'], 'nit'],
            'estado inválido' => ['proveedores', ['nombre' => 'Prueba', 'activo' => 'incorrecto'], 'activo'],
            'objeto en nombre' => ['clientes', ['nombre' => ['objeto'], 'nit' => 'CF'], 'nombre'],
        ];
    }

    public function test_busqueda_combina_texto_estado_y_orden(): void
    {
        Proveedor::factory()->create(['nombre' => 'Zulu', 'contacto' => 'Contacto norte', 'activo' => true]);
        Proveedor::factory()->create(['nombre' => 'Alfa', 'contacto' => 'Contacto norte', 'activo' => true]);
        Proveedor::factory()->create(['nombre' => 'Inactivo', 'contacto' => 'Contacto norte', 'activo' => false]);
        $this->getJson('/api/proveedores/buscar?q=norte&activo=1&orden=nombre&dir=desc')
            ->assertOk()->assertJsonCount(2)->assertJsonPath('0.nombre', 'Zulu')->assertJsonPath('1.nombre', 'Alfa');
        $this->getJson('/api/proveedores/buscar?activo=0')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Inactivo');
    }

    public function test_busqueda_trata_comodines_y_regex_como_texto(): void
    {
        Cliente::factory()->create(['nombre' => 'Cliente .*_% literal', 'nit' => '123']);
        Cliente::factory()->create(['nombre' => 'Otro cliente', 'nit' => '456']);
        $this->getJson('/api/clientes/buscar?'.http_build_query(['q' => '.*_%']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nit', '123');
        $this->getJson('/api/clientes/buscar?q=456')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nit', '456');
        $this->getJson('/api/clientes/buscar?q=sincoincidencias')->assertOk()->assertExactJson([]);
    }

    public function test_parametros_de_busqueda_invalidos_devuelven_422(): void
    {
        $this->getJson('/api/proveedores/buscar?orden=password&dir=incorrecto&activo=otro')
            ->assertUnprocessable()->assertJsonValidationErrors(['orden', 'dir', 'activo']);
        $this->getJson('/api/clientes/buscar?q[]=objeto')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    #[DataProvider('referencias')]
    public function test_registros_con_referencias_no_se_eliminan(string $modulo, string $modelo, string $coleccion, string $campo): void
    {
        $registro = $modelo::factory()->create();
        DB::connection('mongodb')->table($coleccion)->insert([$campo => (string) $registro->getKey()]);
        $this->deleteJson('/api/'.$modulo.'/'.$registro->getKey())->assertConflict();
        $this->assertNotNull($registro->fresh());
    }

    public static function referencias(): array
    {
        return [
            'proveedor con productos' => ['proveedores', Proveedor::class, 'productos', 'proveedor_id'],
            'proveedor con ingresos' => ['proveedores', Proveedor::class, 'ingresos', 'proveedor_id'],
            'cliente con ventas' => ['clientes', Cliente::class, 'ventas', 'cliente_id'],
        ];
    }

    public function test_id_inexistente_o_invalido_devuelve_404(): void
    {
        foreach (['proveedores', 'clientes'] as $modulo) {
            foreach (['000000000000000000000000', 'no-es-un-id'] as $id) {
                $this->getJson('/api/'.$modulo.'/'.$id)->assertNotFound();
                $this->putJson('/api/'.$modulo.'/'.$id, ['nombre' => 'Prueba', 'nit' => 'CF'])->assertNotFound();
                $this->deleteJson('/api/'.$modulo.'/'.$id)->assertNotFound();
            }
        }
        $this->getJson('/api/clientes/por-nit/inexistente')->assertNotFound();
    }

    public function test_sucursales_expone_id_para_editar_y_eliminar_desde_vue(): void
    {
        $respuesta = $this->postJson('/api/sucursales', ['nombre' => 'Sucursal de prueba'])
            ->assertCreated()->assertJsonStructure(['datos' => ['_id']]);
        $id = $respuesta->json('datos._id');
        $this->assertSame(1, DB::connection('mongodb')->table('sucursals')->where('nombre', 'Sucursal de prueba')->count());
        $this->getJson('/api/sucursales/buscar?q=Sucursal%20de%20prueba')->assertOk()->assertJsonPath('0._id', $id);
        $this->putJson('/api/sucursales/'.$id, ['nombre' => 'Sucursal editada'])->assertOk();
        $this->deleteJson('/api/sucursales/'.$id)->assertOk();
        $this->getJson('/api/sucursales/'.$id)->assertNotFound();
    }
}
