<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ValidadorClinico.php';

/**
 * HU-35 — "Las entradas se validan antes de guardar" (VD-VAC-01, VD-HC-03).
 *
 * Antes de este cambio los tres registrarAjax() tomaban $_POST y lo insertaban
 * tal cual. Estas pruebas cubren la capa de validacion en si; el corte de la
 * peticion vive en el controlador, que termina en exit() y por eso no se puede
 * ejercitar desde PHPUnit.
 */
class ValidadorClinicoTest extends TestCase
{
    // ---------------------------------------------------------------- id ---

    public function testIdAceptaEnterosPositivos()
    {
        $this->assertSame(1, ValidadorClinico::id(1));
        $this->assertSame(42, ValidadorClinico::id('42'));
        $this->assertSame(7, ValidadorClinico::id(7.0));
    }

    public function testIdRechazaLoQueNoEsUnIdentificadorReal()
    {
        $this->assertNull(ValidadorClinico::id(0), 'cero no es un id valido');
        $this->assertNull(ValidadorClinico::id(-3), 'los negativos no son ids');
        $this->assertNull(ValidadorClinico::id('3abc'), 'una cadena mixta no es un id');
        $this->assertNull(ValidadorClinico::id('abc'));
        $this->assertNull(ValidadorClinico::id(''));
        $this->assertNull(ValidadorClinico::id(null));
        $this->assertNull(ValidadorClinico::id([1]), 'un arreglo no debe romper la validacion');
        $this->assertNull(ValidadorClinico::id(2.5), 'un decimal no es un id');
    }

    /** Un id_mascota manipulado no puede llegar como inyeccion a la consulta. */
    public function testIdRechazaIntentosDeInyeccion()
    {
        $this->assertNull(ValidadorClinico::id("1 OR 1=1"));
        $this->assertNull(ValidadorClinico::id("1; DROP TABLE mascotas"));
        $this->assertNull(ValidadorClinico::id("1 UNION SELECT password FROM usuarios"));
    }

    // ------------------------------------------------------------- fecha ---

    public function testFechaAceptaSoloFechasRealesEnFormatoISO()
    {
        $this->assertSame('2026-03-15', ValidadorClinico::fecha('2026-03-15'));
        $this->assertSame('2024-02-29', ValidadorClinico::fecha('2024-02-29'), '2024 es bisiesto');
    }

    public function testFechaRechazaFechasInexistentesOMalFormadas()
    {
        $this->assertNull(ValidadorClinico::fecha('2026-02-31'), 'febrero no tiene 31 dias');
        $this->assertNull(ValidadorClinico::fecha('2025-02-29'), '2025 no es bisiesto');
        $this->assertNull(ValidadorClinico::fecha('2026-13-01'), 'no hay mes 13');
        $this->assertNull(ValidadorClinico::fecha('15/03/2026'), 'formato no ISO');
        $this->assertNull(ValidadorClinico::fecha('ayer'));
        $this->assertNull(ValidadorClinico::fecha(''));
        $this->assertNull(ValidadorClinico::fecha(null));
    }

    /** No se puede registrar una vacuna aplicada manana. */
    public function testFechaNoFuturaRechazaElFuturoYAceptaHoy()
    {
        $hoy = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));
        $ayer = date('Y-m-d', strtotime('-1 day'));

        $this->assertSame($hoy, ValidadorClinico::fechaNoFutura($hoy));
        $this->assertSame($ayer, ValidadorClinico::fechaNoFutura($ayer));
        $this->assertNull(ValidadorClinico::fechaNoFutura($manana));
        $this->assertNull(ValidadorClinico::fechaNoFutura('2099-01-01'));
    }

    // ------------------------------------------------------------- texto ---

    public function testTextoRequeridoRecortaYExigeContenido()
    {
        $this->assertSame('Parvovirus', ValidadorClinico::textoRequerido('  Parvovirus  ', 150));
        $this->assertNull(ValidadorClinico::textoRequerido('   ', 150), 'solo espacios es vacio');
        $this->assertNull(ValidadorClinico::textoRequerido('', 150));
        $this->assertNull(ValidadorClinico::textoRequerido(null, 150));
    }

    /** Mejor rechazar que dejar que MySQL trunque en silencio. */
    public function testTextoRequeridoRechazaLoQueNoCabeEnLaColumna()
    {
        $this->assertNotNull(ValidadorClinico::textoRequerido(str_repeat('a', 150), 150));
        $this->assertNull(ValidadorClinico::textoRequerido(str_repeat('a', 151), 150));
    }

    /** En observaciones se prefiere recortar antes que perder el registro. */
    public function testTextoOpcionalNuncaFallaYRecortaAlLimite()
    {
        $this->assertSame('', ValidadorClinico::textoOpcional(null, 100));
        $this->assertSame('', ValidadorClinico::textoOpcional('   ', 100));
        $this->assertSame('nota', ValidadorClinico::textoOpcional(' nota ', 100));
        $this->assertSame(100, mb_strlen(ValidadorClinico::textoOpcional(str_repeat('x', 500), 100)));
    }

    // ------------------------------------------------------- signos vitales ---

    public function testDecimalRespetaLosRangosClinicos()
    {
        $min = ValidadorClinico::PESO_MIN;
        $max = ValidadorClinico::PESO_MAX;

        $this->assertSame(5.5, ValidadorClinico::decimal('5.5', $min, $max));
        $this->assertSame($min, ValidadorClinico::decimal($min, $min, $max), 'el limite inferior entra');
        $this->assertSame($max, ValidadorClinico::decimal($max, $min, $max), 'el limite superior entra');
        $this->assertNull(ValidadorClinico::decimal('0', $min, $max), 'una mascota no pesa 0');
        $this->assertNull(ValidadorClinico::decimal('-5', $min, $max));
        $this->assertNull(ValidadorClinico::decimal('500', $min, $max), 'fuera de rango');
        $this->assertNull(ValidadorClinico::decimal('mucho', $min, $max));
        $this->assertNull(ValidadorClinico::decimal([5], $min, $max));
    }

    public function testTemperaturaFueraDeRangoFisiologicoSeRechaza()
    {
        $min = ValidadorClinico::TEMP_MIN;
        $max = ValidadorClinico::TEMP_MAX;

        $this->assertSame(38.5, ValidadorClinico::decimal('38.5', $min, $max));
        $this->assertNull(ValidadorClinico::decimal('385', $min, $max), 'punto decimal olvidado');
        $this->assertNull(ValidadorClinico::decimal('0', $min, $max));
    }

    public function testEnteroRespetaRangoYRechazaDecimales()
    {
        $min = ValidadorClinico::FC_MIN;
        $max = ValidadorClinico::FC_MAX;

        $this->assertSame(140, ValidadorClinico::entero('140', $min, $max));
        $this->assertNull(ValidadorClinico::entero('140.5', $min, $max), 'la frecuencia es entera');
        $this->assertNull(ValidadorClinico::entero('5', $min, $max), 'por debajo del minimo');
        $this->assertNull(ValidadorClinico::entero('9999', $min, $max), 'por encima del maximo');
    }

    // -------------------------------------------------------------- enum ---

    /**
     * MySQL guarda '' cuando un ENUM recibe un valor que no existe, asi que la
     * lista se valida en PHP o el dato entra corrupto sin avisar.
     */
    public function testOpcionSoloAceptaValoresDelEnum()
    {
        $tipos = ['interna', 'externa'];

        $this->assertSame('interna', ValidadorClinico::opcion('interna', $tipos));
        $this->assertSame('externa', ValidadorClinico::opcion('  EXTERNA  ', $tipos), 'normaliza espacios y mayusculas');
        $this->assertNull(ValidadorClinico::opcion('mixta', $tipos));
        $this->assertNull(ValidadorClinico::opcion('', $tipos));
        $this->assertNull(ValidadorClinico::opcion(null, $tipos));
        $this->assertNull(ValidadorClinico::opcion(['interna'], $tipos));
    }

    public function testPeriodicidadSoloAceptaLasTresDelEnum()
    {
        $periodos = ['mensual', 'trimestral', 'semestral'];

        $this->assertSame('trimestral', ValidadorClinico::opcion('trimestral', $periodos));
        $this->assertNull(ValidadorClinico::opcion('anual', $periodos), 'anual no existe en la columna');
        $this->assertNull(ValidadorClinico::opcion('quincenal', $periodos));
    }
}
