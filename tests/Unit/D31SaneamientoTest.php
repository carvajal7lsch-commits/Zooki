<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorClinica.php';

/** D3.1: el servidor normaliza espacios y rechaza lo que el formulario no permite. */
final class D31SaneamientoTest extends TestCase
{
    public function testSaneamientoDeNombresTelefonosCorreosYSecretos(): void
    {
        $this->assertSame("María O'Neill-Peña", ValidadorCuenta::normalizar('nombre_completo', " \tMaría  O'Neill-Peña\u{00a0}"));
        $this->assertNull(ValidadorCuenta::nombre("María O'Neill-Peña"));
        $this->assertNull(ValidadorCuenta::nombre('Ana María Muñoz'));
        $this->assertNotNull(ValidadorCuenta::nombre('Ana 123'));
        $this->assertNotNull(ValidadorCuenta::nombre('<Ana>'));
        $this->assertNotNull(ValidadorCuenta::nombre(str_repeat('ñ', 101)));
        $this->assertSame('+57 300-1234567', ValidadorTelefono::normalizar(" \t+57   300-1234567\u{00a0}"));
        $this->assertFalse(ValidadorTelefono::esValido('300ABC1234'));
        $this->assertFalse(ValidadorTelefono::esValido(str_repeat('1', 21)));
        $this->assertSame('ana@zooki.test', ValidadorCuenta::normalizar('email', ' ANA@ZOOKI.TEST '));
        $this->assertNotNull(ValidadorCuenta::correo('ana @zooki.test'));
        $this->assertNotNull(ValidadorCuenta::correo(str_repeat('a', 255) . '@zooki.test'));
        $this->assertNull(ValidadorCuenta::documento(' 1023456789 '));
        $this->assertNotNull(ValidadorCuenta::documento('10234a6789'));
        $this->assertNotNull(ValidadorCuenta::documento(str_repeat('1', 16)));
        $this->assertSame(' Clave con espacios ', ValidadorCuenta::normalizar('password', ' Clave con espacios '));
    }

    public function testNombreDeClinicaDireccionYNitCompartenCaracteresYMaximos(): void
    {
        $this->assertSame('Clínica 24/7 & Mascotas', ValidadorCuenta::normalizar('nombre_clinica', '  Clínica   24/7 & Mascotas  '));
        $this->assertNull(ValidadorClinica::campo('nombre_clinica', 'Clínica 24/7 & Mascotas'));
        $this->assertNull(ValidadorClinica::campo('direccion', 'Carrera 7 # 12-34, local (2)'));
        $this->assertNotNull(ValidadorClinica::campo('nombre_clinica', '<Clínica>'));
        $this->assertNotNull(ValidadorClinica::campo('nombre_clinica', str_repeat('A', 151)));
        $this->assertNotNull(ValidadorClinica::campo('direccion', str_repeat('A', 256)));
        $this->assertNull(ValidadorClinica::campo('nit', ' 900123456 '));
        $this->assertNull(ValidadorClinica::campo('nit', '900.123.456-8'), 'RE-0.1.3: pegar limpia puntos y espacios.');
        foreach (['900123456a8', '900123456--8', '90012345-68', '900123456-88', str_repeat('9', 26)] as $nit) {
            $this->assertNotNull(ValidadorClinica::campo('nit', $nit), $nit);
        }
    }

    public function testLosAtributosUsanLosLimitesDelServidor(): void
    {
        $this->assertStringContainsString('maxlength="15"', ValidadorCuenta::atributosHtml('documento'));
        $this->assertStringContainsString('inputmode="numeric"', ValidadorCuenta::atributosHtml('documento'));
        $this->assertStringContainsString('maxlength="100"', ValidadorCuenta::atributosHtml('nombre_completo'));
        $this->assertStringContainsString('maxlength="255"', ValidadorCuenta::atributosHtml('email'));
        $this->assertStringContainsString('data-max-bytes="72"', ValidadorCuenta::atributosHtml('password'));
        $this->assertStringContainsString('maxlength="' . ValidadorNit::PRESENTACION_MAX . '"', ValidadorClinica::atributosHtml('nit'));
        $this->assertStringContainsString('data-nit-numero-max="' . ValidadorNit::NUMERO_MAX . '"', ValidadorClinica::atributosHtml('nit'));
        $this->assertStringContainsString('maxlength="150"', ValidadorClinica::atributosHtml('nombre_clinica'));
        $this->assertStringContainsString('maxlength="255"', ValidadorClinica::atributosHtml('direccion'));
        $this->assertStringContainsString('maxlength="20"', ValidadorTelefono::atributosHtml());
    }
}
