<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../helpers/MotorTriage.php';

final class MotorTriageTest extends TestCase
{
    public static function bateria(): iterable
    {
        foreach (require __DIR__ . '/../Fixtures/triage.php' as $codigo => $caso) {
            yield $codigo => [$caso];
        }
    }

    #[DataProvider('bateria')]
    public function testBateriaConConocimientoReal(array $caso): void
    {
        $grafo = ConocimientoRealPrueba::instantanea();
        $paciente = $caso['paciente'];
        if (isset($paciente['raza_codigo'])) {
            $nodo = $grafo->nodos[$paciente['raza_codigo']];
            $paciente['raza'] = ['codigo' => $nodo['codigo'], 'atributos' => $nodo['metadatos']];
        }
        $resultado = MotorTriage::calcular($caso['sintomas'], $caso['exposiciones'], $paciente, $grafo, new DateTimeImmutable('2026-10-15T12:00:00-05:00'));
        foreach ($caso['esperado'] as $campo => $valor) {
            // B2.1 — Revisión: solo se exige revisión donde la batería la pide.
            if ($valor !== null) {
                $this->assertSame($valor, $resultado[$campo], $campo . ': ' . json_encode($resultado, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    private function calcular(array $codigos, array $paciente = [], array $exposiciones = [], ?ConocimientoClinico $grafo = null): array
    {
        $sintomas = array_fill_keys(array_map(static fn (string $codigo): string => 'sintoma.' . $codigo, $codigos), ['minutos' => null]);
        return MotorTriage::calcular($sintomas, $exposiciones, $paciente + ['especie' => 'especie.canino'], $grafo ?? ConocimientoRealPrueba::instantanea(), new DateTimeImmutable('2026-10-15T12:00:00-05:00'));
    }

    public function testP1TodasLasAlarmasSonUniversalesInclusoFrenteAVerdeOAmarillo(): void
    {
        $grafo = ConocimientoRealPrueba::instantanea();
        $alarmas = array_filter($grafo->nodos, static fn (array $nodo): bool => (bool) ($nodo['alarma_universal'] ?? false));
        $this->assertCount(9, $alarmas);
        foreach ($alarmas as $codigo => $nodo) {
            foreach (['especie.canino', 'especie.ave', 'especie.roedor'] as $especie) {
                $resultado = $this->calcular([substr($codigo, 8), 'control_preventivo', 'otro_no_se'], ['especie' => $especie]);
                $this->assertSame('rojo', $resultado['nivel'], $codigo);
                $this->assertFalse($resultado['revision']);
            }
        }
    }

    public function testP2MaximoYContagioSonIndependientes(): void
    {
        $resultado = $this->calcular(['control_preventivo', 'diarrea_sangre', 'convulsion_en_curso'], ['vacunacion' => 'incompleta']);
        $this->assertSame('rojo', $resultado['nivel']);
        $this->assertTrue($resultado['contagio']);
    }

    public function testP3CuentaModeradosDistintosYEscalaUnaSolaVez(): void
    {
        $this->assertSame('amarillo', $this->calcular(['vomito', 'vomito', 'vomito'])['nivel']);
        $this->assertSame('amarillo', $this->calcular(['decaimiento'])['nivel']);
        $this->assertSame('naranja', $this->calcular(['sed_aumentada', 'no_come', 'decaimiento'])['nivel']);
        $resultado = $this->calcular(['vomito_repetido', 'no_bebe', 'decaimiento', 'encias_secas'], ['edad_meses' => 120]);
        $this->assertSame('naranja', $resultado['nivel']);
        $this->assertSame(1, count(array_keys($resultado['reglas_aplicadas'], 'P3', true)));
    }

    public function testP4DatoDesconocidoNoSeInventaYMarcaRevision(): void
    {
        $resultado = $this->calcular(['arcadas_improductivas', 'abdomen_distendido']);
        $this->assertSame('naranja', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
        $this->assertContains('raza.atributos.tamanio', array_column($resultado['datos_faltantes'], 'campo'));
        $grande = $this->calcular(['arcadas_improductivas', 'abdomen_distendido'], ['raza' => ['atributos' => ['tamanio' => 'grande']]]);
        $this->assertSame('rojo', $grande['nivel']);
    }

    public function testP5ChocolateConVentanasDistintasYTiempoDesconocido(): void
    {
        foreach ([[0, 'naranja'], [719, 'naranja'], [720, 'amarillo'], [2880, 'amarillo'], [null, 'amarillo']] as [$minutos, $nivel]) {
            $resultado = $this->calcular([], [], ['sustancia.chocolate' => ['minutos' => $minutos]]);
            $this->assertSame($nivel, $resultado['nivel']);
            $this->assertTrue($resultado['revision']);
        }
    }

    public function testP6ReservasNuncaTerminanEnVerdeYConservanRevisionInmediata(): void
    {
        foreach ([[], ['otro_no_se'], ['ictericia']] as $marcas) {
            $resultado = $this->calcular($marcas);
            $this->assertSame('amarillo', $resultado['nivel']);
            $this->assertTrue($resultado['revision']);
        }
        $resultado = $this->calcular(['control_preventivo'], ['especie' => 'especie.ave']);
        $this->assertSame('amarillo', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
        $resultado = $this->calcular(['no_come', 'no_defeca'], ['especie' => 'especie.roedor']);
        $this->assertTrue($resultado['revision_inmediata']);
    }

    public function testDecisionesToxicidadPartoYAbdomen(): void
    {
        foreach (['especie.canino', 'especie.felino'] as $especie) {
            $resultado = $this->calcular([], ['especie' => $especie], ['sustancia.etilenglicol' => ['minutos' => null]]);
            $this->assertSame('rojo', $resultado['nivel']);
        }
        $resultado = $this->calcular([], [], ['sustancia.uvas_pasas' => ['minutos' => null]]);
        $this->assertSame('naranja', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
        $resultado = $this->calcular(['parto_esfuerzo_menos_30']);
        $this->assertSame('naranja', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
    }

    public function testDeterminismoSinNotaLibreCuotasNiMora(): void
    {
        $primero = $this->calcular(['control_preventivo']);
        $this->assertSame($primero, $this->calcular(['control_preventivo']));
        $this->assertSame($primero, $this->calcular(['control_preventivo'], ['nota' => 'convulsiona y no respira', 'texto_libre' => 'sangrado', 'cuota' => 0, 'mora' => true]));
        $this->assertSame('rojo', $this->calcular(['colapso'], ['cuota' => 0, 'mora' => true])['nivel']);
    }
}
