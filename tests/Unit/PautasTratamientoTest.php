<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ArchivosConocimiento.php';
require_once __DIR__ . '/../../helpers/MotorPrescripcion.php';

/** Datos exclusivamente sintéticos; las dosis no son una recomendación clínica. */
final class PautasTratamientoTest extends TestCase
{
    private function datos(): array
    {
        return ArchivosConocimiento::leer(__DIR__ . '/../Fixtures/conocimiento/pautas')['datos'];
    }

    private function sugerir(?float $peso, array $alergias = [], array $vigentes = [], ?array $contexto = null): array
    {
        $datos = $this->datos();
        $grafo = new ConocimientoClinico($datos['nodos'], $datos['aristas'], [], 'version-sintetica', 'Pendiente');
        return MotorPrescripcion::sugerir('diagnostico.prueba', $peso, $contexto ?? ['especie' => 'especie.prueba'], $alergias, $vigentes, $grafo, new DateTimeImmutable('2026-10-30'));
    }

    public function testAceptaPautasYPlan(): void
    {
        ValidadorConocimiento::validar($this->datos());
        $this->addToAssertionCount(1);
    }

    public function testRechazaPautasPlanesYFuentesInvalidas(): void
    {
        $mutaciones = [
            static function (array &$d): void {
                unset($d['aristas'][1]['pauta']);
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['extra'] = 1;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['dosis_texto'] = 'Ambas';
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['dosis_mg_kg'] = null;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['dosis_mg_kg']['minimo'] = -1;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['dosis_mg_kg']['minimo'] = 2;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['dosis_mg_kg']['maximo'] = INF;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['via'] = 'inventada';
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['frecuencia_horas'] = 0;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['duracion_dias'] = 1.5;
            },
            static function (array &$d): void {
                $d['aristas'][1]['pauta']['indicacion'] = str_repeat('a', 161);
            },
            static function (array &$d): void {
                $d['aristas'][1]['fuentes'][0]['fundamento'] = 'heuristico';
            },
            static function (array &$d): void {
                $d['aristas'][1]['fuentes'][0]['localizador'] = '';
            },
            static function (array &$d): void {
                $d['aristas'][1]['origen'] = 'sintoma.prueba';
            },
            static function (array &$d): void {
                $d['aristas'][1]['destino'] = 'sustancia.prueba';
            },
            static function (array &$d): void {
                $d['aristas'][1]['signo'] = 'negativo';
            },
            static function (array &$d): void {
                $d['aristas'][1]['condiciones'] = ['predicado' => 'sexo', 'valor' => 'Macho'];
            },
            static function (array &$d): void {
                $d['aristas'][1]['condiciones'] = ['alguno' => [$d['aristas'][1]['condiciones'], ['predicado' => 'sexo', 'valor' => 'Macho']]];
            },
            static function (array &$d): void {
                $d['nodos'][3]['metadatos']['plan']['examenes'] = array_fill(0, 9, 'Prueba');
            },
            static function (array &$d): void {
                $d['nodos'][3]['metadatos']['plan']['examenes'] = [str_repeat('a', 81)];
            },
            static function (array &$d): void {
                $d['nodos'][3]['metadatos']['plan']['recomendaciones'] = array_fill(0, 7, 'Prueba');
            },
            static function (array &$d): void {
                $d['nodos'][3]['metadatos']['plan']['recomendaciones'] = [str_repeat('a', 161)];
            },
            static function (array &$d): void {
                $d['nodos'][3]['metadatos']['plan']['extra'] = [];
            },
            static function (array &$d): void {
                $d['nodos'][0]['metadatos']['plan'] = ['examenes' => [], 'recomendaciones' => []];
            },
        ];
        foreach ($mutaciones as $indice => $mutacion) {
            $datos = $this->datos();
            $mutacion($datos);
            try {
                ValidadorConocimiento::validar($datos);
                $this->fail('Debió rechazar la mutación ' . $indice);
            } catch (InvalidArgumentException $error) {
                $this->assertMatchesRegularExpression('/(aristas|nodos)\.json/', $error->getMessage());
            }
        }
    }

    public function testDosisConPesoSinPesoRangoYTextoYOrden(): void
    {
        $filas = $this->sugerir(24)['tratamientos'];
        $this->assertSame(['trata.prueba', 'trata.rango', 'trata.texto', 'trata.alergeno'], array_column($filas, 'codigo'));
        $this->assertSame('12 mg (0,5 mg/kg) cada 24 h', $filas[0]['dosis']);
        $this->assertSame('12–24 mg (0,5–1 mg/kg) cada 24 h', $filas[1]['dosis']);
        $this->assertSame('1 gota de prueba · dosis única', $filas[2]['dosis']);
        $this->assertSame('según evolución', $filas[2]['duracion']);
        $this->assertSame('Ótica', $filas[2]['via_administracion']);
        $this->assertSame('0,5 mg/kg · calcula con el peso cada 24 h', $this->sugerir(null)['tratamientos'][0]['dosis']);
        $this->assertSame('0,005–0,01 mg (0,5–1 mg/kg) cada 24 h', $this->sugerir(0.01)['tratamientos'][1]['dosis']);
        $this->assertSame([], $this->sugerir(24, [], [], ['especie' => 'especie.otra'])['tratamientos']);
        $this->assertSame([], $this->sugerir(24, [], [], ['especie' => null])['tratamientos']);
    }

    public function testBloqueoPorToxicidadYAlergiaYAdvertenciaProtegidaSinEvidencia(): void
    {
        $respuesta = $this->sugerir(24, [['farmaco' => 'farmaco.alergeno']], [['farmaco' => 'farmaco.vigente', 'visible' => false, 'id' => 999]]);
        $this->assertSame(['trata.prueba', 'trata.rango', 'trata.texto'], array_column($respuesta['tratamientos'], 'codigo'));
        $seguridad = $respuesta['tratamientos'][0]['seguridad'];
        $this->assertTrue($seguridad['requiere_decision']);
        $this->assertTrue($seguridad['requiere_conciliacion']);
        $this->assertSame('interaccion_protegida', $seguridad['advertencias'][0]['tipo']);
        $json = json_encode($respuesta);
        foreach (['farmaco.vigente', 'interaccion.prueba', '999', 'medicacion', 'arista'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $json);
        }
    }
}
