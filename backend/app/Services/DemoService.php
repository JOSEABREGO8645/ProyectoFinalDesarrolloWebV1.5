<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Repositories\DemoRepository;

class DemoService
{
    public function __construct(private DemoRepository $repository, private SucursalService $sucursales, private ProveedorService $proveedores,
        private ClienteService $clientes, private ProductoService $productos, private UsuarioService $usuarios, private OperacionService $operaciones) {}

    public function validar(string $base, array $accesos): void
    {
        if (! app()->environment(['local', 'testing']) || ! preg_match('/^libreria_demo(?:_test_[a-f0-9]{16})?$/D', $base)) {
            throw new ReglaNegocioException('La preparación solo admite bases de demostración en local/testing.');
        }
        $this->repository->validar($base, hash('sha256', json_encode($accesos, JSON_THROW_ON_ERROR)));
    }

    public function preparar(string $base, array $accesos): array
    {
        $this->validar($base, $accesos);
        $token = bin2hex(random_bytes(16));
        if (! $this->repository->reservar(hash('sha256', json_encode($accesos, JSON_THROW_ON_ERROR)), $token)) {
            return ['nueva' => false, 'resumen' => $this->repository->resumen()];
        }
        try {
            $this->catalogosYOperaciones($accesos);
            $this->repository->terminar($token, true);
        } catch (\Throwable $e) {
            $this->repository->terminar($token, false);
            throw $e;
        }

        return ['nueva' => true, 'resumen' => $this->repository->resumen()];
    }

    private function catalogosYOperaciones(array $accesos): void
    {
        $sucursales = [];
        $proveedores = [];
        $clientes = [];
        $productos = [];
        $usuarios = [];
        foreach (['Central', 'Norte', 'Sur', 'Oriente', 'Occidente'] as $i => $nombre) {
            $nombre = 'DEMO - '.$nombre;
            $sucursales[] = $this->repository->encontrar('sucursales', 'nombre', $nombre) ?? $this->sucursales->agregar(['nombre' => $nombre, 'direccion' => 'Dirección ficticia '.($i + 1), 'telefono' => '9900000'.($i + 1), 'activo' => true]);
            $nombre = 'Proveedor ficticio '.($i + 1);
            $proveedores[] = $this->repository->encontrar('proveedores', 'nombre', $nombre) ?? $this->proveedores->agregar(['nombre' => $nombre, 'telefono' => '9900010'.($i + 1), 'correo' => 'proveedor'.($i + 1).'@example.com', 'direccion' => 'Dirección ficticia', 'activo' => true]);
            $nit = 'DEMO000'.($i + 1);
            $clientes[] = $this->repository->encontrar('clientes', 'nit', $nit) ?? $this->clientes->agregar(['nombre' => 'Cliente ficticio '.($i + 1), 'nit' => $nit, 'telefono' => '9900020'.($i + 1), 'correo' => 'cliente'.($i + 1).'@example.com', 'direccion' => 'Dirección ficticia']);
        }
        $nombres = ['Cuaderno de líneas', 'Lápiz de grafito', 'Carpeta escolar', 'Marcador permanente', 'Libro de lectura', 'Borrador blanco', 'Regla escolar', 'Papel de colores', 'Pegamento escolar', 'Tijera escolar'];
        $costos = ['2.50', '0.75', '5.00', '3.00', '12.00', '1.50', '2.25', '4.50', '2.00', '6.00'];
        $precios = ['4.00', '1.50', '8.00', '5.00', '18.00', '2.50', '3.50', '7.00', '3.25', '9.50'];
        foreach ($nombres as $i => $nombre) {
            $codigo = '000-DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $productos[] = $this->repository->encontrar('productos', 'codigo', $codigo) ?? $this->productos->agregar(['codigo' => $codigo, 'nombre' => 'DEMO - '.$nombre, 'categoria' => $i === 4 ? 'Libros' : 'Útiles escolares',
                'precio_costo' => $costos[$i], 'precio_venta' => $precios[$i], 'stock_minimo' => $i % 2 === 0 ? 5 : 10, 'proveedor_id' => (string) $proveedores[intdiv($i, 2)]->getKey(), 'activo' => true]);
        }
        $perfiles = [['superusuario', [], 0], ['empleado', ['ventas', 'clientes'], 0], ['empleado', ['catalogo', 'ingresos', 'traslados'], 0], ['empleado', ['reportes'], 0], ['empleado', ['traslados'], 1]];
        foreach ($accesos['cuentas'] as $i => $cuenta) {
            [$rol,$modulos,$sucursal] = $perfiles[$i];
            $usuarios[] = $this->repository->encontrar('usuarios', 'usuario', $cuenta['usuario']) ?? $this->usuarios->agregar(array_merge($cuenta, ['nombre' => 'DEMO - '.$cuenta['usuario'], 'rol' => $rol, 'modulos' => $modulos, 'sucursal_id' => (string) $sucursales[$sucursal]->getKey(), 'activo' => true]));
        }
        $actor = $usuarios[0];
        $codigo = $accesos['cuentas'][0]['codigo'];
        $paso = 0;
        // La captura por referencia conserva la secuencia al reanudar una carga.
        $uuid = function () use (&$paso): string {
            return '14000000-0000-4000-8000-'.str_pad((string) ++$paso, 12, '0', STR_PAD_LEFT);
        };
        foreach ($sucursales as $i => $sucursal) {
            $actor->setAttribute('sucursal_id', (string) $sucursal->getKey());
            $items = [['producto_id' => (string) $productos[$i * 2]->getKey(), 'cantidad' => 20], ['producto_id' => (string) $productos[$i * 2 + 1]->getKey(), 'cantidad' => 10]];
            $base = ['sucursal_id' => (string) $sucursal->getKey(), 'motivo' => 'Datos ficticios para demostración', 'items' => $items];
            $this->operaciones->crear('ingresos', array_merge($base, ['operacion_id' => $uuid(), 'proveedor_id' => (string) $proveedores[$i]->getKey()]), $actor);
            $base['items'][0]['cantidad'] = 3;
            $base['items'][1]['cantidad'] = 2;
            $this->operaciones->crear('ventas', array_merge($base, ['operacion_id' => $uuid(), 'cliente_id' => (string) $clientes[$i]->getKey(), 'codigo' => $codigo, 'efectivo' => '100.00']), $actor);
        }
        foreach ([[0, 1, 2, 'confirmar'], [2, 3, 2, null], [4, 0, 1, 'anular']] as [$origen,$destino,$cantidad,$accion]) {
            $actor->setAttribute('sucursal_id', (string) $sucursales[$origen]->getKey());
            $doc = $this->operaciones->crear('traslados', ['operacion_id' => $uuid(), 'sucursal_id' => (string) $sucursales[$origen]->getKey(), 'destino_id' => (string) $sucursales[$destino]->getKey(),
                'motivo' => 'Traslado ficticio de demostración', 'items' => [['producto_id' => (string) $productos[$origen * 2]->getKey(), 'cantidad' => $cantidad]]], $actor);
            if ($accion !== null && $doc->estado === 'enviado') {
                $contexto = clone $actor;
                $contexto->setAttribute('sucursal_id', $accion === 'confirmar' ? $doc->destino_id : $doc->origen_id);
                $this->operaciones->finalizar('traslados', (string) $doc->getKey(), $accion, 'Demostración del historial', $contexto);
            }
        }
        foreach (['ventas', 'ingresos'] as $i => $tipo) {
            $actor->setAttribute('sucursal_id', (string) $sucursales[$i]->getKey());
            $base = ['operacion_id' => $uuid(), 'sucursal_id' => (string) $sucursales[$i]->getKey(), 'motivo' => 'Documento ficticio para anular', 'items' => [['producto_id' => (string) $productos[$i * 2]->getKey(), 'cantidad' => 1]]];
            $extra = $tipo === 'ventas' ? ['codigo' => $codigo, 'efectivo' => '100.00'] : ['proveedor_id' => (string) $proveedores[$i]->getKey()];
            $doc = $this->operaciones->crear($tipo, array_merge($base, $extra), $actor);
            if ($doc->estado !== 'anulado') {
                $this->operaciones->finalizar($tipo, (string) $doc->getKey(), 'anular', 'Anulación ficticia de demostración', $actor);
            }
        }
    }
}
