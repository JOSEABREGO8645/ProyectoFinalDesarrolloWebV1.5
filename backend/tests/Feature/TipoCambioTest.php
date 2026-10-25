<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\CatalogoTestCase;

class TipoCambioTest extends CatalogoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'integraciones.banguat_url' => 'https://banguat.test/TipoCambio.asmx']);
        Http::preventStrayRequests();
    }

    private function xml(?string $fecha = null, string $valor = '7.650000'): string
    {
        $fecha ??= now('America/Guatemala')->format('d/m/Y');

        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><TipoCambioDiaResponse xmlns="http://www.banguat.gob.gt/variables/ws/"><TipoCambioDiaResult><CambioDolar><VarDolar><fecha>'.$fecha.'</fecha><referencia>'.$valor.'</referencia></VarDolar></CambioDolar></TipoCambioDiaResult></TipoCambioDiaResponse></soap:Body></soap:Envelope>';
    }

    public function test_consulta_soap_y_conversion_decimal_exacta_con_cache(): void
    {
        Http::fake(['*' => Http::response($this->xml(), 200, ['Content-Type' => 'text/xml'])]);
        $this->getJson('/api/integraciones/tipo-cambio?monto_usd=10.01')->assertOk()->assertJsonPath('referencia', '7.650000')->assertJsonPath('conversion.gtq', '76.58')->assertJsonPath('en_cache', false);
        $this->getJson('/api/integraciones/tipo-cambio?monto_usd=0')->assertOk()->assertJsonPath('conversion.gtq', '0.00')->assertJsonPath('en_cache', true);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->hasHeader('SOAPAction', '"http://www.banguat.gob.gt/variables/ws/TipoCambioDia"') && str_contains($r->body(), 'TipoCambioDia'));
    }

    public function test_xml_invalido_entidades_fecha_y_referencia_no_se_aceptan_ni_cachean(): void
    {
        $xml = '';
        Http::fake(function ($r) use (&$xml) {
            return Http::response($xml);
        });
        foreach (['no es xml', '<!DOCTYPE foo [<!ENTITY x SYSTEM "file:///no-leer">]><foo>&x;</foo>', '<Fault/>', $this->xml('31/02/2026'), $this->xml(now('America/Guatemala')->addDay()->format('d/m/Y')), $this->xml(now('America/Guatemala')->subDays(11)->format('d/m/Y')), $this->xml(null, '0'), $this->xml(null, '-1'), $this->xml(null, '1001')] as $xml) {
            $this->getJson('/api/integraciones/tipo-cambio')->assertStatus(502);
        }
    }

    public function test_timeout_y_error_http_no_inventan_cambio(): void
    {
        $intento = 0;
        Http::fake(function ($r) use (&$intento) {
            if ($intento++ === 0) {
                throw new ConnectionException;
            }

            return Http::response('No disponible', 503);
        });
        $this->getJson('/api/integraciones/tipo-cambio')->assertStatus(503)->assertJsonMissingPath('referencia');
        $this->getJson('/api/integraciones/tipo-cambio')->assertStatus(503)->assertJsonMissingPath('referencia');
    }

    public function test_importes_invalidos_permisos_y_sesion(): void
    {
        foreach (['-1', 'texto', '1.001', '100000000', 'NaN'] as $monto) {
            $this->getJson('/api/integraciones/tipo-cambio?monto_usd='.$monto)->assertUnprocessable();
        }
        $usuario = Usuario::factory()->create(['rol' => 'empleado', 'modulos' => ['reportes']]);
        $token = $this->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => 'PruebaSegura2026!'])->assertOk()->json('access_token');
        $this->withToken($token)->getJson('/api/integraciones/tipo-cambio')->assertForbidden();
        $this->flushHeaders();
        $this->getJson('/api/integraciones/tipo-cambio')->assertUnauthorized();
        Http::assertNothingSent();
    }
}
