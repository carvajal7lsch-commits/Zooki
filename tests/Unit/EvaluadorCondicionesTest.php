<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/EvaluadorCondiciones.php';

final class EvaluadorCondicionesTest extends TestCase
{
    private function evaluar(array $regla, array $contexto): array
    {
        return EvaluadorCondiciones::evaluar($regla, $contexto, new DateTimeImmutable('2026-10-15T12:00:00-05:00'));
    }

    public function testTablasDeVerdadCompletasDeTodosYAlguno(): void
    {
        foreach ([true, false, null] as $a) {
            foreach ([true, false, null] as $b) {
                $contexto = ['esterilizado' => $a, 'raza' => ['atributos' => ['braquicefalo' => $b]]];
                $hojas = [['predicado' => 'esterilizado', 'valor' => true], ['predicado' => 'raza', 'atributo' => 'braquicefalo', 'valor' => true]];
                $todos = ($a === false || $b === false) ? false : (($a === null || $b === null) ? null : true);
                $alguno = ($a === true || $b === true) ? true : (($a === null || $b === null) ? null : false);
                $this->assertSame($todos, $this->evaluar(['todos' => $hojas], $contexto)['valor']);
                $this->assertSame($alguno, $this->evaluar(['alguno' => $hojas], $contexto)['valor']);
            }
        }
        $this->assertSame(['valor' => true, 'faltantes' => []], EvaluadorCondiciones::evaluar(null, [], new DateTimeImmutable('2026-10-15')));
    }

    public function testLosDocePredicadosConDatosPresentesYAusentes(): void
    {
        $casos = [
            [['predicado' => 'sintoma_marcado', 'codigo' => 'sintoma.a'], ['sintomas' => ['sintoma.a' => ['minutos' => null]]]],
            [['predicado' => 'especie', 'codigo' => 'especie.canino'], ['especie' => 'especie.canino']],
            [['predicado' => 'raza', 'atributo' => 'tamanio', 'valor' => 'gigante'], ['raza' => ['atributos' => ['tamanio' => 'gigante']]]],
            [['predicado' => 'edad_meses', 'minimo' => 3, 'incluye_minimo' => true], ['edad_meses' => 3]],
            [['predicado' => 'sexo', 'valor' => 'Hembra'], ['sexo' => 'Hembra']],
            [['predicado' => 'esterilizado', 'valor' => false], ['esterilizado' => false]],
            [['predicado' => 'alerta_activa', 'codigo' => 'condicion.a'], ['alertas_activas' => ['condicion.a']]],
            [['predicado' => 'inicio_episodio', 'codigo' => 'sintoma.a', 'maximo' => 30, 'incluye_maximo' => true], ['sintomas' => ['sintoma.a' => ['minutos' => 30]]]],
            [['predicado' => 'minutos_desde_exposicion', 'codigo' => 'sustancia.a', 'maximo' => 30, 'incluye_maximo' => true], ['exposiciones' => ['sustancia.a' => ['minutos' => 30]]]],
            [['predicado' => 'exposicion', 'codigo' => 'sustancia.a'], ['exposiciones' => ['sustancia.a' => ['minutos' => null]]]],
            [['predicado' => 'vacunacion', 'valor' => 'incompleta'], ['vacunacion' => 'incompleta']],
            [['predicado' => 'dias_desde_celo', 'minimo' => 35, 'incluye_minimo' => true], ['dias_desde_celo' => 35]],
        ];
        $this->assertCount(12, $casos);
        foreach ($casos as [$regla, $contexto]) {
            $this->assertTrue($this->evaluar($regla, $contexto)['valor'], $regla['predicado']);
            $desconocido = $this->evaluar($regla, []);
            $this->assertNull($desconocido['valor'], $regla['predicado']);
            $this->assertNotEmpty($desconocido['faltantes']);
        }
        foreach ([
            [['predicado' => 'especie', 'codigo' => 'especie.canino'], ['especie' => 'especie.felino']],
            [['predicado' => 'sexo', 'valor' => 'Hembra'], ['sexo' => 'Macho']],
            [['predicado' => 'vacunacion', 'valor' => 'completa'], ['vacunacion' => 'incompleta']],
            [['predicado' => 'alerta_activa', 'codigo' => 'condicion.a'], ['alertas_activas' => []]],
            [['predicado' => 'sintoma_marcado', 'codigo' => 'sintoma.a'], ['sintomas' => []]],
            [['predicado' => 'exposicion', 'codigo' => 'sustancia.a'], ['exposiciones' => []]],
        ] as [$regla, $contexto]) {
            $this->assertFalse($this->evaluar($regla, $contexto)['valor']);
        }
    }

    public function testLimitesInclusivosExclusivosYCeroEnTodosLosIntervalos(): void
    {
        foreach (['edad_meses', 'dias_desde_celo', 'inicio_episodio', 'minutos_desde_exposicion'] as $predicado) {
            foreach ([false, true] as $incluido) {
                $regla = ['predicado' => $predicado, 'minimo' => 0, 'incluye_minimo' => $incluido, 'maximo' => 30, 'incluye_maximo' => $incluido];
                foreach ([0 => $incluido, 15 => true, 30 => $incluido, 31 => false] as $dato => $esperado) {
                    if (in_array($predicado, ['edad_meses', 'dias_desde_celo'], true)) {
                        $contexto = [$predicado => $dato];
                    } else {
                        $regla['codigo'] = 'evento.a';
                        $campo = $predicado === 'inicio_episodio' ? 'sintomas' : 'exposiciones';
                        $contexto = [$campo => ['evento.a' => ['minutos' => $dato]]];
                    }
                    $this->assertSame($esperado, $this->evaluar($regla, $contexto)['valor']);
                }
            }
        }
    }

    public function testTiempoDesconocidoNoEsCeroYLaFechaSeInyecta(): void
    {
        $regla = ['predicado' => 'inicio_episodio', 'codigo' => 'sintoma.a', 'maximo' => 30, 'incluye_maximo' => false];
        $this->assertNull($this->evaluar($regla, ['sintomas' => ['sintoma.a' => ['minutos' => null]]])['valor']);
        $this->assertFalse($this->evaluar($regla, ['sintomas' => []])['valor']);
        $inicio = new DateTimeImmutable('2026-10-15T11:45:00-05:00');
        $contexto = ['sintomas' => ['sintoma.a' => ['desde' => $inicio]]];
        $this->assertTrue($this->evaluar($regla, $contexto)['valor']);
        $this->assertFalse(EvaluadorCondiciones::evaluar($regla, $contexto, $inicio->modify('+30 minutes'))['valor']);
        $this->assertNull(EvaluadorCondiciones::evaluar($regla, $contexto, $inicio->modify('-1 minute'))['valor']);
    }
}
