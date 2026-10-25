<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use App\Repositories\PublicacionDatosRepository;
use App\Services\OperacionService;
use App\Services\PublicacionDatosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\MongoTestCase;

class ImportacionPublicacionTest extends MongoTestCase
{
    private Database $destino;

    private string $nombreDestino;

    private PublicacionDatosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $origen = DB::connection('mongodb')->getMongoDB();
        $this->nombreDestino = 'libreria_importacion_test_'.bin2hex(random_bytes(8));
        $this->destino = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase($this->nombreDestino);
        $this->service = new PublicacionDatosService(new PublicacionDatosRepository($origen, ['erp' => $this->destino]));
        $this->artisan('migrate', ['--database' => 'mongodb', '--path' => 'database/migrations/2026_10_23_232450_crear_indices_inventario.php'])->assertExitCode(0)->run();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->nombreDestino) && preg_match('/^libreria_importacion_test_[a-f0-9]{16}$/', $this->nombreDestino)) {
                $this->destino->drop();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function respaldo(): string
    {
        return storage_path('app/private/pruebas-importacion/'.bin2hex(random_bytes(8)));
    }

    private function prepararProducto(): Producto
    {
        $proveedor = Proveedor::factory()->create();

        return Producto::factory()->create(['proveedor_id' => (string) $proveedor->getKey(), 'codigo' => 'IMPORT-001', 'precio_costo' => '1.23', 'precio_venta' => '2.34', 'categoria' => 'Prueba']);
    }

    public function test_importa_documentos_saldos_y_kardex_sin_duplicar_y_con_respaldo_bson(): void
    {
        $usuario = Usuario::factory()->create(['rol' => 'superusuario']);
        $producto = $this->prepararProducto();
        app(OperacionService::class)->crear('ingresos', ['operacion_id' => (string) Str::uuid(), 'proveedor_id' => $producto->proveedor_id, 'items' => [['producto_id' => (string) $producto->getKey(), 'cantidad' => 2]], 'motivo' => 'Recepcion original'], $usuario);
        DB::connection('mongodb')->getCollection('auth_sessions')->insertOne(['usuario_id' => (string) $usuario->getKey(), 'refresh_hash' => 'exclusivo-del-origen']);
        $this->destino->selectCollection('clientes')->insertOne(['_id' => new ObjectId, 'nombre' => 'Existente', 'nit' => 'EXISTENTE']);
        $plan = $this->service->preparar();
        $directorio = $this->respaldo();
        $this->service->aplicar($plan, $directorio);
        $copiado = $this->destino->selectCollection('productos')->findOne(['codigo' => 'IMPORT-001']);
        $this->assertSame((string) $producto->getKey(), (string) $copiado['_id']);
        $this->assertSame('1.23', (string) $copiado['precio_costo']);
        $this->assertSame(2, $this->destino->selectCollection('existencias')->findOne()['cantidad']);
        $this->assertSame(1, $this->destino->selectCollection('movimientos')->countDocuments());
        $this->assertSame(1, $this->destino->selectCollection('clientes')->countDocuments());
        $this->assertSame(0, $this->destino->selectCollection('auth_sessions')->countDocuments());
        $this->assertFileExists($directorio.'/manifest.json');
        $this->assertStringContainsString('$numberDecimal', file_get_contents($directorio.'/origen-erp-productos.jsonl'));
        $nuevo = $this->service->preparar();
        $this->assertSame(0, array_sum(array_column($nuevo['resumen'], 'agregar')));
        $this->service->aplicar($nuevo, $this->respaldo());
        $this->assertSame(1, $this->destino->selectCollection('movimientos')->countDocuments());
    }

    public function test_rechaza_clave_duplicada_con_identificador_distinto(): void
    {
        $this->prepararProducto();
        $this->destino->selectCollection('productos')->insertOne(['codigo' => 'import-001']);
        $this->expectException(ReglaNegocioException::class);
        $this->service->preparar();
    }

    public function test_no_sobrescribe_datos_cambiados_en_destino(): void
    {
        $this->prepararProducto();
        $this->service->aplicar($this->service->preparar(), $this->respaldo());
        $this->destino->selectCollection('productos')->updateOne(['codigo' => 'IMPORT-001'], ['$set' => ['nombre' => 'Modificado en linea']]);
        $this->expectException(ReglaNegocioException::class);
        $this->service->preparar();
    }

    public function test_detecta_escritura_concurrente_antes_de_importar(): void
    {
        $this->prepararProducto();
        $plan = $this->service->preparar();
        $this->destino->selectCollection('clientes')->insertOne(['nombre' => 'Creado despues del plan', 'nit' => 'NUEVO']);
        try {
            $this->service->aplicar($plan, $this->respaldo());
            $this->fail('Debio detenerse por el cambio concurrente.');
        } catch (ReglaNegocioException) {
            $this->assertSame(0, $this->destino->selectCollection('productos')->countDocuments());
            $this->assertSame(1, $this->destino->selectCollection('clientes')->countDocuments());
        }
    }

    public function test_revierte_la_importacion_completa_si_falla_una_coleccion(): void
    {
        $producto = $this->prepararProducto();
        Producto::factory()->create(['codigo' => 'IMPORT-002', 'proveedor_id' => $producto->proveedor_id, 'categoria' => 'Prueba']);
        $this->destino->selectCollection('productos')->createIndex(['categoria' => 1], ['unique' => true]);
        try {
            $this->service->aplicar($this->service->preparar(), $this->respaldo());
            $this->fail('Debio fallar el indice artificial.');
        } catch (BulkWriteException) {
            $this->assertSame(0, $this->destino->selectCollection('productos')->countDocuments());
            $this->assertSame(0, $this->destino->selectCollection('proveedores')->countDocuments());
        }
    }
}
