<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorClinica.php';

final class ValidadorClinicaTest extends TestCase
{
    public function testNitConPesosDianYNormalizacion(): void
    {
        $this->assertSame(8, ValidadorNit::digito('900123456'));
        $this->assertSame(1, ValidadorNit::digito('4'));
        foreach (['900123456-8', '900.123.456-8', '900123456', '0900123456-8'] as $nit) {
            $this->assertNull(ValidadorNit::validar($nit));
            $this->assertSame('9001234568', ValidadorNit::normalizarEntrada($nit));
        }
        foreach (['900123456-2', 'abc', '', '900123456-11', '900123456--1'] as $nit) {
            $this->assertNotNull(ValidadorNit::validar($nit));
        }
    }

    public function testDominiosDesechablesRechazadosEnAmbosCorreos(): void
    {
        foreach (['email_clinica', 'email_administrador'] as $campo) {
            foreach (['persona@YOPMAIL.COM', 'persona@sub.mailinator.com'] as $email) {
                $this->assertSame('No se admiten correos temporales.', ValidadorClinica::campo($campo, $email));
            }
            $this->assertNull(ValidadorClinica::campo($campo, 'permanente@zooki.test'));
            $this->assertNotNull(ValidadorClinica::campo($campo, 'sin-arroba'));
        }
    }
}
