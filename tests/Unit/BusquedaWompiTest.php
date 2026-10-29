<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ClienteWompiHttp.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';

final class BusquedaWompiTest extends TestCase
{
    public function testBusquedaPrivadaExactaSinFiltrosNiPost(): void
    {
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion(), function ($url, $cabeceras, $metodo, $datos): array {
            $this->assertSame('https://sandbox.wompi.co/v1/transactions?reference=zk_prueba', $url);
            $this->assertContains('Authorization: Bearer prv_test_falsa', $cabeceras);
            $this->assertSame('GET', $metodo);
            $this->assertNull($datos);
            return ['codigo' => 200, 'cuerpo' => '{"data":[],"meta":{}}'];
        });
        $this->assertSame([], $cliente->transaccionesPorReferencia('zk_prueba'));
    }

    public function testSoloListaCompletaValidaPuedeProbarAusencia(): void
    {
        foreach (['{"data":{}}', '{"data":null}', '{}', 'null', 'error',
            '{"data":[],"meta":{"next_page":2}}', '{"data":[],"pagination":{"total":1}}',
            '{"data":[{"id":"tx_1","reference":"otra","status":"PENDING"}]}',
            '{"data":[{"id":"tx_1","reference":"zk_prueba","status":"DESCONOCIDO"}]}'] as $cuerpo) {
            $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion(), static fn () => ['codigo' => 200, 'cuerpo' => $cuerpo]);
            try {
                $cliente->transaccionesPorReferencia('zk_prueba');
                $this->fail('Una respuesta dudosa permitió liberar la reserva: ' . $cuerpo);
            } catch (RuntimeException | JsonException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion(), static fn () => ['codigo' => 404, 'cuerpo' => '{"data":[]}']);
        $this->expectException(RuntimeException::class);
        $cliente->transaccionesPorReferencia('zk_prueba');
    }

    public function testDevuelveTodosLosEstadosYBloqueaRedReal(): void
    {
        $lista = array_map(static fn ($estado) => ['id' => 'tx_' . $estado, 'reference' => 'zk_prueba', 'status' => $estado], ['DECLINED', 'PENDING', 'APPROVED', 'ERROR', 'VOIDED']);
        $cliente = new ClienteWompiHttp(ClienteWompiFalso::configuracion(), static fn () => ['codigo' => 200, 'cuerpo' => json_encode(['data' => $lista])]);
        $this->assertSame($lista, $cliente->transaccionesPorReferencia('zk_prueba'));
        $this->expectException(LogicException::class);
        (new ClienteWompiHttp(ClienteWompiFalso::configuracion()))->transaccionesPorReferencia('zk_prueba');
    }
}
