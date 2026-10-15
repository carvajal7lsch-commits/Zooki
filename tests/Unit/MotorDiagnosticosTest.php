<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../helpers/MotorDiagnosticos.php';

final class MotorDiagnosticosTest extends TestCase
{
    private function sugerir(array $sintomas, array $contexto = []): array
    {
        $contexto['sintomas'] = array_fill_keys($sintomas, ['minutos' => null]);
        return MotorDiagnosticos::sugerir(ConocimientoRealPrueba::instantanea(), $contexto, new DateTimeImmutable('2026-10-15T12:00:00-05:00'));
    }

    public function testRankingRealConLosCuatroPacientesDelContrato(): void
    {
        foreach ([
            [['sintoma.vomito', 'sintoma.diarrea_sangre', 'sintoma.no_come'], ['especie' => 'especie.canino', 'edad_meses' => 3, 'vacunacion' => 'incompleta'], 'diagnostico.parvovirosis_canina'],
            [['sintoma.intentos_orinar_minimo'], ['especie' => 'especie.felino', 'sexo' => 'Macho'], 'diagnostico.obstruccion_uretral_felina'],
            [['sintoma.arcadas_improductivas', 'sintoma.abdomen_distendido'], ['especie' => 'especie.canino', 'raza' => ['codigo' => 'raza.gran_danes', 'atributos' => ['tamanio' => 'gigante']]], 'diagnostico.dilatacion_torsion_gastrica'],
            [['sintoma.sed_aumentada', 'sintoma.decaimiento', 'sintoma.secrecion_genital'], ['especie' => 'especie.canino', 'sexo' => 'Hembra', 'esterilizado' => false, 'dias_desde_celo' => 35], 'diagnostico.piometra'],
        ] as [$sintomas, $contexto, $esperado]) {
            $resultado = $this->sugerir($sintomas, $contexto);
            $this->assertSame($esperado, $resultado['candidatos'][0]['codigo']);
            $this->assertSame($resultado, $this->sugerir($sintomas, $contexto));
            $this->assertNotEmpty($resultado['candidatos'][0]['fuentes']);
            $this->assertSame('pendiente', $resultado['candidatos'][0]['estado_validacion']);
        }
    }

    public function testGatoNoRecibeDiagnosticosSoloCaninosYNuncaMasDeCinco(): void
    {
        $grafo = ConocimientoRealPrueba::instantanea();
        $sintomas = array_column(array_filter($grafo->nodos, static fn (array $n): bool => $n['tipo'] === 'sintoma'), 'codigo');
        $resultado = $this->sugerir($sintomas, ['especie' => 'especie.felino']);
        $this->assertCount(5, $resultado['candidatos']);
        $codigos = array_column($resultado['candidatos'], 'codigo');
        foreach (['diagnostico.parvovirosis_canina', 'diagnostico.tos_perreras', 'diagnostico.dilatacion_torsion_gastrica', 'diagnostico.gastroenteritis_hemorragica'] as $canino) {
            $this->assertNotContains($canino, $codigos);
        }
        $this->assertDoesNotMatchRegularExpression('/probabilidad|porcentaje|%/i', json_encode($resultado['candidatos'], JSON_UNESCAPED_UNICODE));
    }

    public function testSinEvidenciaNoHayCandidatosAunqueExistanModificadores(): void
    {
        $this->assertSame([], $this->sugerir([], ['especie' => 'especie.canino', 'edad_meses' => 3, 'vacunacion' => 'incompleta'])['candidatos']);
        $this->assertSame([], $this->sugerir(['sintoma.otro_no_se'])['candidatos']);
    }

    public function testCondicionDesconocidaSeInformaYNoAportaPeso(): void
    {
        $incompleto = $this->sugerir(['sintoma.intentos_orinar_minimo'], ['especie' => 'especie.felino']);
        $completo = $this->sugerir(['sintoma.intentos_orinar_minimo'], ['especie' => 'especie.felino', 'sexo' => 'Macho']);
        $a = array_column($incompleto['candidatos'], null, 'codigo');
        $b = array_column($completo['candidatos'], null, 'codigo');
        $this->assertEqualsWithDelta(0.3, $b['diagnostico.obstruccion_uretral_felina']['puntuacion'] - $a['diagnostico.obstruccion_uretral_felina']['puntuacion'], 0.00001);
        $this->assertContains(['elemento' => 'ranking.obstruccion_macho', 'campos' => ['sexo']], $incompleto['datos_faltantes']);
        $this->assertSame([], $completo['datos_faltantes']);
        $sinEspecie = $this->sugerir(['sintoma.intentos_orinar_minimo']);
        $this->assertSame([], $sinEspecie['candidatos']);
        $this->assertNotEmpty($sinEspecie['datos_faltantes']);
    }

    public function testSignosCoberturaYDesempatePorCodigoSinContarDuplicados(): void
    {
        $nodos = [
            ['codigo' => 's.a', 'tipo' => 'sintoma'], ['codigo' => 's.b', 'tipo' => 'sintoma'],
            ['codigo' => 'd.a', 'tipo' => 'diagnostico', 'etiqueta' => 'A', 'fuentes' => []],
            ['codigo' => 'd.b', 'tipo' => 'diagnostico', 'etiqueta' => 'B', 'fuentes' => []],
            ['codigo' => 'd.c', 'tipo' => 'diagnostico', 'etiqueta' => 'C', 'fuentes' => []],
        ];
        $aristas = [];
        foreach ([['d.a', 's.a', 'positivo', 0.5], ['d.a', 's.b', 'positivo', 0.5], ['d.b', 's.a', 'positivo', 0.5], ['d.c', 's.a', 'positivo', 1], ['d.c', 's.a', 'negativo', 0.5]] as $i => [$destino, $origen, $signo, $peso]) {
            $aristas[] = ['codigo' => 'a.' . $i, 'origen' => $origen, 'destino' => $destino, 'tipo_relacion' => 'sugiere', 'signo' => $signo, 'peso' => $peso, 'fuentes' => []];
        }
        $grafo = new ConocimientoClinico($nodos, $aristas, [], null, 'Prueba');
        $resultado = MotorDiagnosticos::sugerir($grafo, ['sintomas' => ['s.a' => []]], new DateTimeImmutable('2026-10-15'));
        $this->assertSame(['d.b', 'd.c', 'd.a'], array_column($resultado['candidatos'], 'codigo'));
        $this->assertSame(['coinciden' => 1, 'caracteristicos' => 2, 'texto' => 'Coincide 1 de 2 síntomas característicos'], $resultado['candidatos'][2]['cobertura']);
        $this->assertSame(0.5, $resultado['candidatos'][1]['puntuacion']);
        $this->assertSame(['s.a'], $resultado['candidatos'][1]['sintomas_coincidentes']);
    }
}
