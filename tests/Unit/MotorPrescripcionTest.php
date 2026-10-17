<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../helpers/MotorPrescripcion.php';
require_once __DIR__ . '/../../helpers/PrescripcionPublica.php';

final class MotorPrescripcionTest extends TestCase
{
    private function evaluar(?string $farmaco, array $contexto, array $alergias = [], array $vigentes = [], array $otras = []): array
    {
        return MotorPrescripcion::evaluar($farmaco, $contexto, $alergias, $vigentes, $otras, ConocimientoRealPrueba::instantanea(), new DateTimeImmutable('2026-10-15'));
    }

    public function testToxicidadRealDeEspecieYRazaDistingueBloqueoYAdvertencia(): void
    {
        foreach ([
            ['farmaco.paracetamol', ['especie' => 'especie.felino'], true],
            ['farmaco.paracetamol', ['especie' => 'especie.canino'], false],
            ['farmaco.permetrina', ['especie' => 'especie.felino'], true],
            ['farmaco.loperamida', ['especie' => 'especie.canino', 'raza' => ['codigo' => 'raza.pastor_aleman']], false],
        ] as [$farmaco, $contexto, $bloquea]) {
            $resultado = $this->evaluar($farmaco, $contexto);
            $this->assertSame($bloquea, $resultado['bloqueada']);
            $this->assertNotEmpty($bloquea ? $resultado['motivos'] : $resultado['advertencias']);
            $this->assertSame(!$bloquea, $resultado['requiere_decision']);
        }
    }

    public function testAlergiaIndividualBloqueaSinAristaBibliograficaYNoSePuedeConfirmar(): void
    {
        $resultado = $this->evaluar('farmaco.prednisona', ['especie' => 'especie.canino'], [['farmaco' => 'farmaco.prednisona', 'id' => 35]]);
        $this->assertTrue($resultado['bloqueada']);
        $this->assertSame('alergia', $resultado['motivos'][0]['tipo']);
        $this->assertFalse($resultado['requiere_decision']);
        $this->assertArrayNotHasKey('fuentes', $resultado['motivos'][0]);
    }

    public function testInteraccionesEnAmbosSentidosYEntreFilasNuevas(): void
    {
        foreach ([['farmaco.meloxicam', 'farmaco.prednisona'], ['farmaco.prednisona', 'farmaco.meloxicam']] as [$farmaco, $otro]) {
            foreach ([false, true] as $nueva) {
                $medicacion = [['farmaco' => $otro, 'visible' => true, 'vigencia' => 'vigente']];
                $resultado = $this->evaluar($farmaco, ['especie' => 'especie.canino'], [], $nueva ? [] : $medicacion, $nueva ? $medicacion : []);
                $this->assertFalse($resultado['bloqueada']);
                $this->assertTrue($resultado['requiere_decision']);
                $this->assertSame('interaccion', $resultado['advertencias'][0]['tipo']);
            }
        }
    }

    public function testIdentidadOVigenciaDesconocidaNuncaDaSeguridadNiAdvertenciaInventada(): void
    {
        $this->assertTrue($this->evaluar(null, [])['evaluacion_incompleta']);
        $sinNodo = $this->evaluar('farmaco.prednisona', [], [], [['farmaco' => null, 'visible' => false]]);
        $this->assertTrue($sinNodo['evaluacion_incompleta']);
        $this->assertSame([], $sinNodo['advertencias']);
        $interactua = $this->evaluar('farmaco.meloxicam', [], [], [['farmaco' => 'farmaco.prednisona', 'visible' => true, 'vigencia' => 'desconocida']]);
        $this->assertTrue($interactua['evaluacion_incompleta']);
        $this->assertStringContainsString('vigencia desconocida', $interactua['advertencias'][0]['mensaje']);
        $noInteractua = $this->evaluar('farmaco.prednisona', [], [], [['farmaco' => 'farmaco.selegilina', 'visible' => false, 'vigencia' => 'desconocida']]);
        $this->assertTrue($noInteractua['evaluacion_incompleta']);
        $this->assertSame([], $noInteractua['advertencias']);
    }

    public function testSalidaProtegidaAgrupaAvisosSinSerializarNingunaEvidenciaInterna(): void
    {
        $vigentes = [
            ['farmaco' => 'farmaco.prednisona', 'visible' => false, 'id' => 998877, 'fecha_inicio' => '2026-01-02', 'id_clinica' => 7345],
            ['farmaco' => 'farmaco.aspirina', 'visible' => false, 'id' => 887766],
        ];
        $interna = $this->evaluar('farmaco.meloxicam', ['especie' => 'especie.canino'], [], $vigentes);
        $publica = PrescripcionPublica::desde($interna, ConocimientoRealPrueba::instantanea());
        $this->assertGreaterThanOrEqual(2, count($interna['advertencias']));
        $this->assertCount(1, $publica['advertencias']);
        $this->assertTrue($publica['requiere_conciliacion']);
        $json = json_encode($publica, JSON_UNESCAPED_UNICODE);
        foreach (['prednisona', 'aspirina', '998877', '887766', '7345', '2026-01-02', 'arista.', 'fuentes'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $json);
        }
        $permitidas = ['bloqueada', 'motivos', 'advertencias', 'evaluacion_incompleta', 'requiere_decision', 'requiere_conciliacion', 'tipo', 'mensaje'];
        $revisar = function (array $objeto) use (&$revisar, $permitidas): void {
            foreach ($objeto as $clave => $valor) {
                if (is_string($clave)) {
                    $this->assertContains($clave, $permitidas);
                }
                if (is_array($valor)) {
                    $revisar($valor);
                }
            }
        };
        $revisar($publica);
    }

    public function testReglasDePrescripcionUsanElEvaluadorYLaFechaInyectada(): void
    {
        $base = ConocimientoRealPrueba::instantanea();
        $regla = ['codigo' => 'prescripcion.prueba', 'motor' => 'prescripcion', 'condiciones' => ['predicado' => 'esterilizado', 'valor' => true], 'resultado' => ['efecto' => 'advertencia', 'motivo' => 'apoyo'], 'fuentes' => []];
        $grafo = new ConocimientoClinico(array_values($base->nodos), $base->aristas, [$regla], $base->version, $base->limitaciones);
        $fecha = new DateTimeImmutable('2026-10-15');
        $ausente = MotorPrescripcion::evaluar('farmaco.prednisona', [], [], [], [], $grafo, $fecha);
        $this->assertTrue($ausente['evaluacion_incompleta']);
        $this->assertSame([], $ausente['advertencias']);
        $presente = MotorPrescripcion::evaluar('farmaco.prednisona', ['esterilizado' => true], [], [], [], $grafo, $fecha);
        $this->assertSame('prescripcion.prueba', $presente['advertencias'][0]['regla']);
        $this->assertSame($presente, MotorPrescripcion::evaluar('farmaco.prednisona', ['esterilizado' => true], [], [], [], $grafo, $fecha));
    }
}
