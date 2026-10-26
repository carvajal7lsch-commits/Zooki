<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../helpers/AutorizacionCobro.php';
require_once __DIR__ . '/../../helpers/ClienteWompiHttp.php';
require_once __DIR__ . '/../../helpers/VigenciaSuscripcion.php';

final class AutorizacionCobroTest extends TestCase
{
    public function testPruebaSinJwtVersionCambianteYCondicionesInvalidas(): void
    {
        $contratos = ClienteWompiFalso::contratos();
        $a = AutorizacionCobro::contratos($contratos);
        $this->assertCount(2, $a['prueba']);
        $this->assertStringNotContainsString('.falso', json_encode($a['prueba']));
        $this->assertSame($a['version'], AutorizacionCobro::contratos($contratos)['version']);
        $this->assertNotSame($a['version'], AutorizacionCobro::contratos(ClienteWompiFalso::contratos('nueva'))['version']);
        foreach ([[], ['presigned_acceptance' => ['permalink' => 'javascript:malicioso']], array_replace($contratos, ['presigned_personal_data_auth' => ['permalink' => 'https://wompi.co/p.pdf', 'acceptance_token' => 'malformado']])] as $invalido) {
            try {
                AutorizacionCobro::contratos($invalido);
                $this->fail('No confiar en contratos incompletos.');
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }

    public function testClientePostConFuenteYTokensNequiConTransporteFalso(): void
    {
        $llamadas = [];
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion('test', '1', '1'), static function ($url, $headers, $metodo, $datos) use (&$llamadas): array {
            $llamadas[] = [$url, $headers, $metodo, $datos];
            return ['codigo' => $metodo === 'GET' ? 200 : 201, 'cuerpo' => '{"data":{"id":123}}'];
        });
        $cliente->crearFuente(['type' => 'CARD', 'token' => 'tok_test_local']);
        $cliente->crearTransaccion(['payment_source_id' => 123, 'amount_in_cents' => 8999000]);
        $cliente->tokenNequi('nequi_test_local');
        $this->assertSame('https://sandbox.wompi.co/v1/payment_sources', $llamadas[0][0]);
        $this->assertContains('Authorization: Bearer prv_test_falsa', $llamadas[0][1]);
        $this->assertSame('POST', $llamadas[1][2]);
        $this->assertSame(8999000, $llamadas[1][3]['amount_in_cents']);
        $this->assertContains('Authorization: Bearer pub_test_falsa', $llamadas[2][1]);
        $this->expectException(InvalidArgumentException::class);
        $cliente->tokenNequi('nequi_prod_cruzado');
    }

    public function testNingunMetodoDelClientePuedeSalirALaRedEnPruebas(): void
    {
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion('test', '1', '1'));
        foreach ([fn () => $cliente->crearFuente([]), fn () => $cliente->crearTransaccion([]), fn () => $cliente->tokenNequi('nequi_test_local'), fn () => $cliente->comercio()] as $llamada) {
            try {
                $llamada();
                $this->fail('La prueba permitió red.');
            } catch (LogicException $error) {
                $this->assertStringContainsString('falso', $error->getMessage());
            }
        }
        $this->assertSame('24/10/2026', VigenciaSuscripcion::fechaIncluida('2026-10-25 00:00:00'));
        $this->assertSame('29/02/2028', VigenciaSuscripcion::fechaIncluida('2028-03-01 00:00:00'));
        $this->assertSame('31/12/2026', VigenciaSuscripcion::fechaIncluida('2027-01-01 00:00:00'));
        $this->assertSame('Sin fecha', VigenciaSuscripcion::fechaIncluida(null));
    }
}
