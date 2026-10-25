<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../helpers/ClienteWompiHttp.php';

final class FirmaWompiTest extends TestCase
{
    public function testVectoresPublicadosYExpiracion(): void
    {
        $this->assertSame('37c8407747e595535433ef8f6a811d853cd943046624a0ec04662b17bbf33bf5', FirmaWompi::integridad('sk8-438k4-xmxm392-sn2m', 2490000, 'COP', 'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'));
        $cuerpo = json_encode(['event' => 'transaction.updated', 'environment' => 'prod', 'timestamp' => 1530291411,
            'data' => ['transaction' => ['id' => '1234-1610641025-49201', 'status' => 'APPROVED', 'amount_in_cents' => 4490000]],
            'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => '5A18EC5E8FDB7DF463E9F94774CBA8F583BA21BD04A09CEFF2EA68A4BC0AEFBE']]);
        $evento = FirmaWompi::evento($cuerpo, '', 'prod', 'prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z');
        $this->assertSame('1234-1610641025-49201', $evento['id_transaccion_wompi']);
        $sin = FirmaWompi::integridad('r', 100, 'COP', 'secreto');
        $this->assertNotSame($sin, FirmaWompi::integridad('r', 100, 'COP', 'secreto', '2026-10-25T00:00:00.000Z'));
        $this->assertNotSame($sin, FirmaWompi::integridad('r', 101, 'COP', 'secreto'));
        $this->assertNotSame($sin, FirmaWompi::integridad('otra', 100, 'COP', 'secreto'));
    }

    public function testPropiedadesVariablesYHuellaSinPii(): void
    {
        $tx = ['id' => 'id_1', 'status' => 'APPROVED', 'amount_in_cents' => 8999000, 'reference' => 'local', 'customer_email' => 'privado@example.com'];
        $cuerpo = ClienteWompiFalso::cuerpo($tx, 'test', 1, ['transaction.reference', 'transaction.amount_in_cents', 'transaction.id', 'transaction.status']);
        $firma = json_decode($cuerpo, true)['signature']['checksum'];
        $evento = FirmaWompi::evento($cuerpo, strtoupper($firma), 'test', 'test_events_falso');
        $this->assertSame($evento, FirmaWompi::evento($cuerpo, '', 'test', 'test_events_falso'));
        $this->assertStringNotContainsString('privado', json_encode($evento));
        $this->assertStringNotContainsString('customer_email', json_encode($evento));
        $this->expectException(UnexpectedValueException::class);
        FirmaWompi::evento($cuerpo, str_repeat('0', 64), 'test', 'test_events_falso');
    }

    public function testNoAceptaIdentificadorSinFirmaNiValorComplejo(): void
    {
        foreach ([['transaction.status'], ['transaction.id', 'transaction.falta'], ['transaction.id', 'transaction.objeto']] as $propiedades) {
            $tx = ['id' => 'x', 'status' => 'APPROVED', 'amount_in_cents' => 1];
            $evento = json_decode(ClienteWompiFalso::cuerpo($tx), true);
            $evento['signature']['properties'] = $propiedades;
            $evento['data']['transaction']['objeto'] = ['no escalar'];
            try {
                FirmaWompi::evento(json_encode($evento), '', 'test', 'test_events_falso');
                $this->fail('Debe rechazar el esquema/firma.');
            } catch (InvalidArgumentException | UnexpectedValueException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }

    public function testHttpConTransporteFalsoPrivadaYMerchantInfoSinRed(): void
    {
        $llamadas = [];
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion(), static function ($url, $headers) use (&$llamadas): array {
            $llamadas[] = [$url, $headers];
            return ['codigo' => 200, 'cuerpo' => '{"data":{"id":"falso"}}'];
        });
        $this->assertSame(['id' => 'falso'], $cliente->transaccion('123-abc'));
        $cliente->comercio();
        $this->assertSame('https://sandbox.wompi.co/v1/transactions/123-abc', $llamadas[0][0]);
        $this->assertContains('Authorization: Bearer prv_test_falsa', $llamadas[0][1]);
        $this->assertSame('https://sandbox.wompi.co/v1/merchants/info', $llamadas[1][0]);
        $this->assertContains('x-merchant-public-key: pub_test_falsa', $llamadas[1][1]);
        $this->expectException(LogicException::class);
        (new ClienteWompiHttp(ClienteWompiFalso::configuracion()))->transaccion('sin_red');
    }

    public function testConfiguracionIncompletaCruzadaYApagadaFallaCerrado(): void
    {
        foreach ([new ConfiguracionWompi([]), ClienteWompiFalso::configuracion('test', '0'), new ConfiguracionWompi(['ENTORNO' => 'produccion', 'LLAVE_PUBLICA' => 'pub_test_falsa'])] as $config) {
            try {
                $config->validar();
                $this->fail('La configuración inválida habilitó pagos.');
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }
}
