<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ArchivosConocimiento.php';

final class ValidadorConocimientoTest extends TestCase
{
    public function testConjuntoSinteticoYRealSonValidos(): void
    {
        // B2 llenó database/conocimiento; sus pruebas propias están en ConocimientoRealTest.
        foreach ([__DIR__ . '/../Fixtures/conocimiento', __DIR__ . '/../../database/conocimiento'] as $carpeta) {
            $lectura = ArchivosConocimiento::leer($carpeta);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $lectura['version']);
            ValidadorConocimiento::validar($lectura['datos']);
        }
    }

    public function testRechazaPredicadosAnidamientoReferenciasFuentesBloqueosYCampos(): void
    {
        $base = ArchivosConocimiento::leer(__DIR__ . '/../Fixtures/conocimiento')['datos'];
        $mutaciones = [
            static function (array &$datos): void {
                $datos['reglas'][0]['condiciones'] = ['predicado' => 'texto_libre', 'valor' => 'algo'];
            },
            static function (array &$datos): void {
                $datos['reglas'][0]['condiciones'] = ['todos' => [['alguno' => [['todos' => [['predicado' => 'sexo', 'valor' => 'Macho']]]]]]];
            },
            static function (array &$datos): void {
                $datos['aristas'][0]['destino'] = 'diagnostico.inexistente';
            },
            static function (array &$datos): void {
                $datos['nodos'][0]['fuentes'] = [];
            },
            static function (array &$datos): void {
                $datos['reglas'][0]['motor'] = 'prescripcion';
                $datos['reglas'][0]['resultado'] = ['efecto' => 'bloqueo', 'motivo' => 'interaccion'];
            },
            static function (array &$datos): void {
                $datos['nodos'][0]['campo_ajeno'] = true;
            },
            static function (array &$datos): void {
                $datos['reglas'][0]['condiciones'] = ['predicado' => 'raza', 'codigo' => 'raza.prueba'];
            },
            static function (array &$datos): void {
                $datos['reglas'][0]['condiciones'] = ['predicado' => 'edad_meses', 'minimo' => 6, 'incluye_minimo' => true, 'unidad' => 'dias'];
            },
            static function (array &$datos): void {
                $datos['aristas'][0]['efecto'] = 'bloqueo';
            },
            static function (array &$datos): void {
                $datos['reglas'][0]['resultado'] = ['efecto' => 'bloqueo', 'motivo' => 'toxicidad'];
            },
            static function (array &$datos): void {
                $datos['nodos'][] = $datos['nodos'][0];
            },
            static function (array &$datos): void {
                $datos['nodos'][2]['alarma_universal'] = true;
            },
        ];
        foreach ($mutaciones as $indice => $mutar) {
            $datos = $base;
            $mutar($datos);
            try {
                ValidadorConocimiento::validar($datos);
                $this->fail('Aceptó la mutación inválida ' . $indice);
            } catch (InvalidArgumentException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testValidaLosDocePredicadosSinEvaluarlos(): void
    {
        $datos = ArchivosConocimiento::leer(__DIR__ . '/../Fixtures/conocimiento')['datos'];
        $predicados = [
            ['predicado' => 'sintoma_marcado', 'codigo' => 'sintoma.prueba'],
            ['predicado' => 'especie', 'codigo' => 'especie.prueba'],
            ['predicado' => 'raza', 'atributo' => 'tamanio', 'valor' => 'gigante'],
            ['predicado' => 'edad_meses', 'maximo' => 6, 'incluye_maximo' => false],
            ['predicado' => 'sexo', 'valor' => 'Hembra'],
            ['predicado' => 'esterilizado', 'valor' => false],
            ['predicado' => 'alerta_activa', 'codigo' => 'farmaco.prueba'],
            ['predicado' => 'inicio_episodio', 'codigo' => 'sintoma.prueba', 'minimo' => 30, 'incluye_minimo' => false],
            ['predicado' => 'minutos_desde_exposicion', 'codigo' => 'sustancia.prueba', 'maximo' => 60, 'incluye_maximo' => true],
            ['predicado' => 'exposicion', 'codigo' => 'sustancia.prueba'],
            ['predicado' => 'vacunacion', 'valor' => 'incompleta'],
            ['predicado' => 'dias_desde_celo', 'minimo' => 14, 'incluye_minimo' => true, 'maximo' => 56, 'incluye_maximo' => true],
        ];
        $this->assertSame(ValidadorConocimiento::PREDICADOS, array_column($predicados, 'predicado'));
        foreach ($predicados as $predicado) {
            $datos['reglas'][0]['condiciones'] = $predicado;
            ValidadorConocimiento::validar($datos);
            $this->assertTrue(true);
        }
        $datos['reglas'][0]['motor'] = 'prescripcion';
        $datos['reglas'][0]['resultado'] = ['efecto' => 'bloqueo', 'motivo' => 'toxicidad'];
        ValidadorConocimiento::validar($datos);
        $datos['reglas'][0]['fuentes'][0]['fundamento'] = 'heuristico';
        $this->expectException(InvalidArgumentException::class);
        ValidadorConocimiento::validar($datos);
    }

    public function testNormalizaSinonimosConTildesYEspacios(): void
    {
        $this->assertSame('vomito con sangre', ArchivosConocimiento::normalizarTexto(" VÓMITO  con\tsangre "));
    }

    public function testVersionIdenticaConFinalesCrLfYLf(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        $carpeta = ConocimientoPrueba::copiar();
        try {
            foreach (glob($carpeta . '/*.json') as $ruta) {
                file_put_contents($ruta, str_replace("\r\n", "\n", file_get_contents($ruta)));
            }
            $lf = ArchivosConocimiento::leer($carpeta);
            foreach (glob($carpeta . '/*.json') as $ruta) {
                file_put_contents($ruta, str_replace("\n", "\r\n", file_get_contents($ruta)));
                $this->assertStringContainsString("\r\n", file_get_contents($ruta));
            }
            $crlf = ArchivosConocimiento::leer($carpeta);
            $this->assertSame($lf, $crlf);
            ConocimientoPrueba::cambiar($carpeta, 'nodos', static function (array &$nodos): void {
                $nodos[0]['etiqueta'] = 'Cambio real de contenido';
            });
            $this->assertNotSame($lf['version'], ArchivosConocimiento::leer($carpeta)['version']);
        } finally {
            ConocimientoPrueba::borrar($carpeta);
        }
    }

    public function testErroresIndicanArchivoCodigoYCampo(): void
    {
        $base = ArchivosConocimiento::leer(__DIR__ . '/../Fixtures/conocimiento')['datos'];
        foreach ([
            ['nodos', 0, 'campo_desconocido', true, 'especie.prueba', 'campo_desconocido'],
            ['nodos', 2, 'etiqueta', '', 'sintoma.prueba', 'etiqueta'],
            ['aristas', 0, 'destino', 'nodo.inexistente', 'arista.prueba', 'destino'],
            ['fuentes', 0, 'fecha_consulta', 'fecha mala', 'fuente.prueba', 'fecha_consulta'],
            ['reglas', 0, 'condiciones', ['predicado' => 'no_existe'], 'regla.prueba', 'predicado'],
        ] as [$archivo, $fila, $campo, $valor, $codigo, $palabra]) {
            $datos = $base;
            $datos[$archivo][$fila][$campo] = $valor;
            try {
                ValidadorConocimiento::validar($datos);
                $this->fail('Debió indicar la ubicación del error.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($archivo . '.json', $error->getMessage());
                $this->assertStringContainsString($codigo, $error->getMessage());
                $this->assertStringContainsString($palabra, $error->getMessage());
            }
        }
    }

    public function testPredicadosNuevosRechazanTipoUnidadYValorIncorrectos(): void
    {
        foreach ([
            ['predicado' => 'exposicion', 'codigo' => 'especie.prueba'],
            ['predicado' => 'vacunacion', 'valor' => 'si'],
            ['predicado' => 'dias_desde_celo', 'minimo' => 14, 'incluye_minimo' => true, 'unidad' => 'horas'],
        ] as $condicion) {
            $datos = ArchivosConocimiento::leer(__DIR__ . '/../Fixtures/conocimiento')['datos'];
            $datos['reglas'][0]['condiciones'] = $condicion;
            try {
                ValidadorConocimiento::validar($datos);
                $this->fail('Predicado no válido aceptado.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('reglas.json [regla.prueba]', $error->getMessage());
            }
        }
    }
}
