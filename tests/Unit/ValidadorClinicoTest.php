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

    // ------------------------------------------- M2 C8 (H8): signos ---

    /**
     * La restricción de entrada del navegador sale del mismo helper que valida:
     * cifras de la columna (decimal(5,2) para el peso) y el máximo del servidor.
     * Las pruebas de JS (M2C8Rediseno) usan estos mismos atributos.
     */
    public function testAtributosDeLosSignosTienenLasCifrasYElRangoDelServidor()
    {
        $peso = ValidadorClinico::atributosSigno('peso');
        $this->assertStringContainsString('type="text" inputmode="decimal"', $peso, 'Sin las flechas ni la e del campo numérico');
        $this->assertStringContainsString('maxlength="6"', $peso, '3 enteros, el punto y 2 decimales');
        $this->assertStringContainsString('data-caracteres="0-9.," data-formato="decimal"', $peso);
        $this->assertStringContainsString('data-enteros="3" data-decimales="2"', $peso);
        $this->assertStringContainsString('data-minimo="0.01" data-maximo="200"', $peso);

        $temperatura = ValidadorClinico::atributosSigno('temperatura');
        $this->assertStringContainsString('maxlength="4"', $temperatura);
        $this->assertStringContainsString('data-enteros="2" data-decimales="1"', $temperatura);
        $this->assertStringContainsString('data-minimo="25" data-maximo="45"', $temperatura);

        $cardiaca = ValidadorClinico::atributosSigno('frecuencia_cardiaca');
        $this->assertStringContainsString('type="text" inputmode="numeric"', $cardiaca);
        $this->assertStringContainsString('maxlength="3" data-caracteres="0-9"', $cardiaca, 'Sin punto: es un entero');
        $this->assertStringContainsString('data-minimo="10" data-maximo="400"', $cardiaca);

        $respiratoria = ValidadorClinico::atributosSigno('frecuencia_respiratoria');
        $this->assertStringContainsString('data-enteros="3" data-decimales="0" data-minimo="5" data-maximo="150"', $respiratoria);
    }

    public function testLosLimitesDeLosSignosSonLosMismosQueValidaElServidor()
    {
        foreach (ValidadorClinico::SIGNOS as $campo => $signo) {
            $maximo = $signo['max'];
            $esDecimal = $signo['decimales'] > 0;
            $valido = $esDecimal ? ValidadorClinico::decimal((string) $maximo, $signo['min'], $signo['max']) : ValidadorClinico::entero((string) $maximo, $signo['min'], $signo['max']);
            $this->assertNotNull($valido, "$campo: el máximo que deja escribir el navegador lo acepta el servidor");
            $this->assertLessThanOrEqual(10 ** $signo['enteros'] - 1, $maximo, "$campo: el máximo cabe en sus cifras enteras");
        }
        $this->assertSame('Entre 0.01 y 200 kg', ValidadorClinico::ayudaSigno('peso'));
        $this->assertSame('Entre 25 y 45 °C', ValidadorClinico::ayudaSigno('temperatura'));
        $this->assertSame('Entre 10 y 400 lpm', ValidadorClinico::ayudaSigno('frecuencia_cardiaca'));
        $this->assertSame('Entre 5 y 150 rpm', ValidadorClinico::ayudaSigno('frecuencia_respiratoria'));
    }
}
