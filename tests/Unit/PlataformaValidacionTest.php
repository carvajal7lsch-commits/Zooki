<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorPlataforma.php';
require_once __DIR__ . '/../../helpers/ValidadorClinica.php';

final class PlataformaValidacionTest extends TestCase
{
    public function testNitUnicoConDvYMaximoCompartido(): void
    {
        $this->assertNull(ValidadorNit::validar('900123456-8'));
        $this->assertNull(ValidadorNit::validar('900123456'));
        $this->assertNotNull(ValidadorNit::validar('900123456-7'));
        $this->assertStringContainsString('maxlength="16"', ValidadorClinica::atributosHtml('nit'));
        $this->assertStringContainsString('inputmode="numeric"', ValidadorClinica::atributosHtml('nit'));
        $this->assertStringContainsString('data-formato="nit"', ValidadorClinica::atributosHtml('nit'));
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
