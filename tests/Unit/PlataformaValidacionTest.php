<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorPlataforma.php';
require_once __DIR__ . '/../../helpers/ValidadorClinica.php';

final class PlataformaValidacionTest extends TestCase
{
    public function testNitYDvSeValidanSeparadosSinAdivinarUltimoDigito(): void
    {
        $this->assertNull(ValidadorNit::validarSeparado('900123456', '8'));
        $this->assertNull(ValidadorNit::validarSeparado(' 900123456 ', ' 8 '));
        foreach ([['900123456', '2'], ['9001234568', ''], ['900123456-8', '8'], ['90012345a', '8'], ['900123456', 'a'], ['900123456', '88'], [str_repeat('9', 15), '1']] as [$nit, $dv]) {
            $this->assertNotNull(ValidadorNit::validarSeparado($nit, $dv));
        }
        $this->assertStringContainsString('maxlength="14"', ValidadorClinica::atributosHtml('nit'));
        $this->assertStringContainsString('maxlength="1"', ValidadorClinica::atributosHtml('dv'));
        $this->assertStringNotContainsString('data-formato', ValidadorClinica::atributosHtml('nit'));
    }

    public function testMotivoSaneaEspaciosYComparteMaximoDelServidor(): void
    {
        $this->assertSame('Revisión del titular', ValidadorPlataforma::texto("  Revisión \n del   titular  "));
        $this->assertSame('Razón: cuenta #2.', ValidadorPlataforma::texto('Razón: cuenta #2.'));
        $this->assertStringContainsString('maxlength="65535"', ValidadorPlataforma::atributos());
        foreach ([' ', "Con\x00trol", str_repeat('ñ', 32768)] as $valor) {
            try {
                ValidadorPlataforma::texto($valor);
                $this->fail('Debe rechazar el texto vacío, inválido o demasiado largo.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('motivo', $e->getMessage());
            }
        }
    }
}
