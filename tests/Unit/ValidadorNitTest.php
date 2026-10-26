<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorNit.php';

final class ValidadorNitTest extends TestCase
{
    public function testNumeroSinDvYConDvExplicitoGuardanElMismoFormato(): void
    {
        foreach (['900123456-8', '900123456', ' 900.123.456 - 8 ', ' 900.123.456 '] as $valor) {
            $this->assertSame(['numero' => '900123456', 'dv' => '8'], ValidadorNit::separar($valor));
            $this->assertNull(ValidadorNit::validar($valor));
            $this->assertSame('9001234568', ValidadorNit::normalizarEntrada($valor));
        }
        $this->assertSame('9001234568', ValidadorNit::normalizar('9001234568'), 'Los datos ya guardados nunca reciben otro DV.');
        $this->assertSame('90012345688', ValidadorNit::normalizarEntrada('9001234568'), 'Sin guion son todas las cifras del número, incluso si su última cifra coincide con un DV.');
    }

    public function testCedulaDeDiezCifrasSeAceptaCompletaSinPedirDv(): void
    {
        $cedula = '1075001234';
        foreach ([$cedula, $cedula . '-5', '1.075.001.234'] as $valor) {
            $this->assertSame(['numero' => $cedula, 'dv' => '5'], ValidadorNit::separar($valor));
            $this->assertNull(ValidadorNit::validar($valor));
            $this->assertSame('10750012345', ValidadorNit::normalizarEntrada($valor));
        }
        $this->assertSame('10750012345', ValidadorNit::separar('10750012345')['numero'], 'La longitud no autoriza a recortar el número sin guion.');
    }

    public function testDvExplicitoErradoUnDigitoSoloYFormatosInvalidos(): void
    {
        foreach (['900123456-7', '1', '', '0-0', '00', '900123456--8', '900123456-88', '900123456x8', str_repeat('9', 15)] as $valor) {
            $this->assertSame('El dígito de verificación no corresponde. Revísalo en tu RUT.', ValidadorNit::validar($valor), $valor);
            try {
                ValidadorNit::normalizarEntrada($valor);
                $this->fail('Una entrada inválida no tiene representación para guardar.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame(ValidadorNit::MENSAJE, $error->getMessage());
            }
        }
    }

    public function testNumeroDeCatorceCifrasConYSinDv(): void
    {
        $numero = '12345678901234';
        $dv = (string) ValidadorNit::digito($numero);
        $this->assertSame($numero . $dv, ValidadorNit::normalizarEntrada($numero));
        $this->assertSame($numero . $dv, ValidadorNit::normalizarEntrada($numero . '-' . $dv));
        $this->assertSame(16, ValidadorNit::PRESENTACION_MAX);
    }
}
