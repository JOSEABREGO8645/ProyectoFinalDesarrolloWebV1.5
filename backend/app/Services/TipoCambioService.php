<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TipoCambioService
{
    public function consultar(?string $monto = null): array
    {
        $hoy = CarbonImmutable::now(config('dashboard.timezone'))->startOfDay();
        $clave = 'banguat:referencia:'.hash('sha256', config('integraciones.banguat_url')).':'.$hoy->toDateString();
        $dato = Cache::get($clave);
        $enCache = is_array($dato);
        if (! $enCache) {
            $dato = $this->solicitar($hoy);
            Cache::put($clave, $dato, config('integraciones.cambio_cache_segundos'));
        }
        $dato['en_cache'] = $enCache;
        if ($monto !== null) {
            $usd = BigDecimal::of($monto)->toScale(2, RoundingMode::HalfUp);
            $dato['conversion'] = ['usd' => (string) $usd, 'gtq' => (string) $usd->multipliedBy($dato['referencia'])->toScale(2, RoundingMode::HalfUp)];
        }

        return $dato;
    }

    private function solicitar(CarbonImmutable $hoy): array
    {
        $opciones = ['allow_redirects' => false, 'verify' => config('integraciones.banguat_ca') ?: true];
        $xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><TipoCambioDia xmlns="http://www.banguat.gob.gt/variables/ws/" /></soap:Body></soap:Envelope>';
        try {
            $r = Http::withHeaders(['SOAPAction' => '"http://www.banguat.gob.gt/variables/ws/TipoCambioDia"', 'Accept' => 'text/xml'])
                ->withBody($xml, 'text/xml; charset=utf-8')->connectTimeout(config('integraciones.conexion_segundos'))->timeout(config('integraciones.respuesta_segundos'))
                ->withOptions($opciones)->post(config('integraciones.banguat_url'));
        } catch (ConnectionException) {
            throw new ReglaNegocioException('No fue posible consultar el tipo de cambio. Intente más tarde.', 503);
        }
        if (! $r->successful()) {
            throw new ReglaNegocioException('El servicio de tipo de cambio no está disponible.', 503);
        }
        $cuerpo = $r->body();
        if (strlen($cuerpo) > 1048576 || preg_match('/<!DOCTYPE|<!ENTITY/i', $cuerpo)) {
            throw new ReglaNegocioException('La respuesta del tipo de cambio no es válida.', 502);
        }
        $doc = simplexml_load_string($cuerpo, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodos = $doc !== false ? $doc->xpath('/*[local-name()="Envelope" and namespace-uri()="http://schemas.xmlsoap.org/soap/envelope/"]/*[local-name()="Body"]/*[local-name()="TipoCambioDiaResponse"]/*[local-name()="TipoCambioDiaResult"]/*[local-name()="CambioDolar"]/*[local-name()="VarDolar"]') : [];
        if (count($nodos) !== 1) {
            throw new ReglaNegocioException('La respuesta del tipo de cambio no contiene una referencia válida.', 502);
        }
        $datos = $nodos[0]->children('http://www.banguat.gob.gt/variables/ws/');
        $fechaTexto = trim((string) $datos->fecha);
        $valor = trim((string) $datos->referencia);
        try {
            $fecha = CarbonImmutable::createFromFormat('!d/m/Y', $fechaTexto, config('dashboard.timezone'));
        } catch (\Throwable) {
            $fecha = null;
        }
        if (! $fecha || $fecha->format('d/m/Y') !== $fechaTexto || $fecha->gt($hoy) || $fecha->lt($hoy->subDays(10)) || ! preg_match('/^\d{1,4}(?:\.\d{1,8})?$/D', $valor)
            || BigDecimal::of($valor)->isLessThanOrEqualTo(0) || BigDecimal::of($valor)->isGreaterThan(1000)) {
            throw new ReglaNegocioException('La referencia recibida no es válida o está desactualizada.', 502);
        }

        return ['fuente' => 'Banco de Guatemala', 'fecha' => $fecha->toDateString(), 'moneda_origen' => 'USD', 'moneda_destino' => 'GTQ',
            'referencia' => (string) BigDecimal::of($valor)->toScale(6, RoundingMode::HalfUp), 'consultado_at' => now()->toIso8601String()];
    }
}
