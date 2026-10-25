<?php

namespace App\Services;

use App\Exceptions\IntegracionExternaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ApiPruebaClient
{
    public function disponible(string $tipo): bool
    {
        $url = config('integraciones.'.$tipo.'.url');
        $token = config('integraciones.'.$tipo.'.token');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT) && is_string($token) && strlen($token) >= 16;
    }

    public function generar(string $tipo, string $clave, array $documento): array
    {
        if (! $this->disponible($tipo)) {
            throw new IntegracionExternaException('pendiente', 'El servicio de pruebas no está configurado. Contacte al administrador.');
        }
        $endpoint = $tipo === 'facturacion' ? '/v1/facturas' : '/v1/comprobantes-pago';
        try {
            $r = Http::acceptJson()->asJson()->withToken(config('integraciones.'.$tipo.'.token'))->withHeaders(['Idempotency-Key' => $clave])
                ->connectTimeout(config('integraciones.conexion_segundos'))->timeout(config('integraciones.respuesta_segundos'))
                ->withOptions(['allow_redirects' => false])->post(rtrim(config('integraciones.'.$tipo.'.url'), '/').$endpoint, $documento);
        } catch (ConnectionException) {
            throw new IntegracionExternaException('pendiente', 'No fue posible confirmar la prueba. Reintente; se conservará la misma solicitud.');
        }
        if (in_array($r->status(), [400, 409, 422], true)) {
            throw new IntegracionExternaException('rechazada', 'El servicio externo rechazó el documento de prueba.', 422);
        }
        if (! $r->successful()) {
            throw new IntegracionExternaException('pendiente', 'El servicio externo no está disponible. Reintente más tarde.');
        }
        $d = strlen($r->body()) <= 65536 ? $r->json() : null;
        $prefijo = $tipo === 'facturacion' ? 'FAC-DEMO-' : 'PAGO-DEMO-';
        if (! is_array($d) || ($d['entorno'] ?? null) !== 'simulado' || ($d['tipo'] ?? null) !== $tipo || ($d['estado'] ?? null) !== 'generado'
            || ($d['idempotency_key'] ?? null) !== $clave || ($d['documento_id'] ?? null) !== $documento['documento_id'] || ($d['moneda'] ?? null) !== 'GTQ'
            || ($d['total_centavos'] ?? null) !== $documento['total_centavos'] || ! is_string($d['referencia'] ?? null) || ! preg_match('/^'.preg_quote($prefijo, '/').'[A-F0-9]{20}$/D', $d['referencia'])
            || ! is_string($d['emitido_at'] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/D', $d['emitido_at'])
            || ! $this->fechaValida($d['emitido_at'])
            || ($d['validez'] ?? null) !== ($tipo === 'facturacion' ? 'SIN_VALOR_FISCAL' : 'SIN_MOVIMIENTO_BANCARIO')) {
            throw new IntegracionExternaException('pendiente', 'La respuesta externa no es válida. No se confirmó la prueba.', 502);
        }

        return ['estado' => 'completada', 'simulado' => true, 'referencia' => $d['referencia'], 'emitido_at' => $d['emitido_at'], 'total_centavos' => $d['total_centavos'], 'validez' => $d['validez']];
    }

    private function fechaValida(string $texto): bool
    {
        $formato = 'Y-m-d\\TH:i:s.v\\Z';
        $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $texto, new \DateTimeZone('UTC'));

        return $fecha !== false && $fecha->format($formato) === $texto;
    }
}
