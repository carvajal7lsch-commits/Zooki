<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ArchivosConocimiento.php';

final class ValidadorConocimientoTest extends TestCase
{
    public function testConjuntoSinteticoYVacioSonValidos(): void
    {
        foreach ([__DIR__ . '/../Fixtures/conocimiento', __DIR__ . '/../../database/conocimiento'] as $carpeta) {
            $lectura = ArchivosConocimiento::leer($carpeta);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $lectura['version']);
            ValidadorConocimiento::validar($lectura['datos']);
        }
        $this->assertSame([], ArchivosConocimiento::leer(__DIR__ . '/../../database/conocimiento')['datos']['nodos']);
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

    public function testValidaLosNuevePredicadosSinEvaluarlos(): void
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
}
