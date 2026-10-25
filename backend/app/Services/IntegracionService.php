<?php

namespace App\Services;

use App\Exceptions\IntegracionExternaException;
use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Repositories\IntegracionRepository;

class IntegracionService
{
    public function __construct(private OperacionService $operaciones, private IntegracionRepository $repository, private ApiPruebaClient $cliente) {}

    public function generar(string $tipo, string $id, Usuario $usuario): array
    {
        $doc = $this->operaciones->buscar($tipo, $id, $usuario);
        $campo = $tipo === 'ventas' ? 'facturacion' : 'pagos';
        $esperado = $tipo === 'ventas' ? 'emitida' : 'registrado';
        if ($doc->estado !== $esperado) {
            throw new ReglaNegocioException('Solo se generan pruebas de documentos vigentes.', 409);
        }
        $anterior = (array) ($doc->integraciones[$campo] ?? []);
        if (($anterior['estado'] ?? null) === 'completada') {
            return $anterior;
        }
        if (! $this->cliente->disponible($campo)) {
            throw new ReglaNegocioException('El servicio de pruebas no está configurado. Contacte al administrador.', 503);
        }
        if ($doc->total_centavos <= 0) {
            throw new ReglaNegocioException('El documento de prueba requiere un importe mayor que cero.');
        }
        $token = bin2hex(random_bytes(16));
        $ahora = now()->timestamp;
        if (! $this->repository->reservar($tipo, $id, $campo, $token, $ahora, $ahora + config('integraciones.lease_segundos'), (string) $usuario->getKey())) {
            $actual = $this->operaciones->buscar($tipo, $id, $usuario);
            $registro = (array) ($actual->integraciones[$campo] ?? []);
            if ($actual->estado === $esperado && ($registro['estado'] ?? null) === 'completada') {
                return $registro;
            }
            throw new ReglaNegocioException('La solicitud está en curso o el documento cambió. Actualice e intente de nuevo.', 409);
        }
        $payload = ['documento_id' => (string) $doc->getKey(), 'numero' => $doc->numero, 'moneda' => 'GTQ', 'total_centavos' => $doc->total_centavos,
            'sucursal' => ['id' => $doc->sucursal_id, 'nombre' => $doc->sucursal_nombre],
            'receptor' => ['nombre' => $tipo === 'ventas' ? $doc->cliente_nombre : $doc->proveedor_nombre, 'identificador' => $tipo === 'ventas' ? $doc->cliente_nit : $doc->proveedor_id],
            'items' => array_map(fn ($i) => ['codigo' => $i['codigo'], 'nombre' => $i['nombre'], 'cantidad' => (int) $i['cantidad'], 'precio_centavos' => (int) $i['precio_centavos'], 'subtotal_centavos' => (int) $i['subtotal_centavos']], $doc->items)];
        $clave = hash('sha256', 'libreria:prueba:'.$campo.':'.$id);
        try {
            $resultado = $this->cliente->generar($campo, $clave, $payload);
        } catch (IntegracionExternaException $e) {
            $this->repository->guardar($tipo, $id, $campo, $token, ['estado' => $e->estadoIntegracion, 'simulado' => true, 'ultimo_error' => $e->getMessage(), 'solicitado_por_id' => (string) $usuario->getKey(), 'actualizado_at' => now()->toIso8601String()]);
            throw $e;
        }
        $resultado['solicitado_por_id'] = (string) $usuario->getKey();
        $resultado['actualizado_at'] = now()->toIso8601String();
        if (! $this->repository->guardar($tipo, $id, $campo, $token, $resultado)) {
            throw new ReglaNegocioException('La confirmación local está pendiente. Reintente con el mismo documento.', 409);
        }

        return $resultado;
    }
}
