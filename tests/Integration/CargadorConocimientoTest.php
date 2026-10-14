<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ConocimientoPrueba.php';

final class CargadorConocimientoTest extends TestCase
{
    private string $carpeta;
    private PDO $db;

    protected function setUp(): void
    {
        $this->carpeta = ConocimientoPrueba::copiar();
        $this->db = ConocimientoPrueba::sqlite();
    }

    protected function tearDown(): void
    {
        ConocimientoPrueba::borrar($this->carpeta);
    }

    public function testCargaRepetidaConservaIdsYNoEscribeNiAuditaOtraVez(): void
    {
        $cargador = new CargadorConocimiento($this->db);
        $primera = $cargador->cargar($this->carpeta);
        $antes = ConocimientoPrueba::instantanea($this->db);
        $segunda = $cargador->cargar($this->carpeta);
        $this->assertSame(9, $primera['altas']);
        $this->assertSame(0, $segunda['altas'] + $segunda['cambios'] + $segunda['retiros']);
        $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        $auditoria = json_decode($antes['auditoria_sistema'][0]['datos_nuevos'], true);
        $this->assertSame($primera['version'], $auditoria['version']);
        $this->assertNull($antes['auditoria_sistema'][0]['id_clinica']);
    }

    public function testEtiquetaRetiroYReintroduccionConservanIdentidad(): void
    {
        $cargador = new CargadorConocimiento($this->db);
        $cargador->cargar($this->carpeta);
        $id = $this->db->query("SELECT id_nodo FROM grafo_nodos WHERE codigo = 'sustancia.prueba'")->fetchColumn();
        ConocimientoPrueba::cambiar($this->carpeta, 'nodos', static function (array &$nodos): void {
            $nodos[5]['etiqueta'] = 'Etiqueta corregida';
        });
        $cargador->cargar($this->carpeta);
        $this->assertSame($id, $this->db->query("SELECT id_nodo FROM grafo_nodos WHERE codigo = 'sustancia.prueba'")->fetchColumn());
        ConocimientoPrueba::cambiar($this->carpeta, 'nodos', static function (array &$nodos): void {
            array_pop($nodos);
        });
        $this->assertSame(1, $cargador->cargar($this->carpeta)['retiros']);
        $this->assertSame(0, (int) $this->db->query('SELECT activo FROM grafo_nodos WHERE id_nodo = ' . $id)->fetchColumn());
        copy(__DIR__ . '/../Fixtures/conocimiento/nodos.json', $this->carpeta . '/nodos.json');
        $cargador->cargar($this->carpeta);
        $this->assertSame(1, (int) $this->db->query('SELECT activo FROM grafo_nodos WHERE id_nodo = ' . $id)->fetchColumn());
        $this->assertSame(6, (int) $this->db->query('SELECT COUNT(*) FROM grafo_nodos')->fetchColumn());
    }

    public function testRevisarYArchivosFallidosNoTocanLoCargado(): void
    {
        $cargador = new CargadorConocimiento($this->db);
        $antes = ConocimientoPrueba::instantanea($this->db);
        $this->assertSame(9, $cargador->cargar($this->carpeta, true)['altas']);
        $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        $cargador->cargar($this->carpeta);
        $antes = ConocimientoPrueba::instantanea($this->db);
        foreach (['faltante', 'json', 'referencia', 'taxonomia'] as $caso) {
            $copia = ConocimientoPrueba::copiar();
            try {
                if ($caso === 'faltante') {
                    unlink($copia . '/aristas.json');
                } elseif ($caso === 'json') {
                    file_put_contents($copia . '/aristas.json', '{no valido');
                } elseif ($caso === 'referencia') {
                    ConocimientoPrueba::cambiar($copia, 'aristas', static function (array &$aristas): void {
                        $aristas[0]['origen'] = 'sintoma.inexistente';
                    });
                } else {
                    ConocimientoPrueba::cambiar($copia, 'nodos', static function (array &$nodos): void {
                        $nodos[1]['taxonomia']['especie'] = 'Felino';
                    });
                }
                try {
                    $cargador->cargar($copia);
                    $this->fail('Debió rechazar: ' . $caso);
                } catch (InvalidArgumentException $error) {
                    $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
                }
            } finally {
                ConocimientoPrueba::borrar($copia);
            }
        }
    }

    public function testErrorDeEscrituraRevierteTodaLaCarga(): void
    {
        $this->db->exec("CREATE TRIGGER impedir_regla BEFORE INSERT ON grafo_reglas BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        try {
            (new CargadorConocimiento($this->db))->cargar($this->carpeta);
            $this->fail('Debió fallar al final del conjunto.');
        } catch (PDOException $error) {
            foreach (ConocimientoPrueba::instantanea($this->db) as $filas) {
                $this->assertSame([], $filas);
            }
            $this->assertFalse($this->db->inTransaction());
        }
    }

    public function testUnCodigoNoSeReutilizaParaOtraEspecie(): void
    {
        $cargador = new CargadorConocimiento($this->db);
        $cargador->cargar($this->carpeta);
        $antes = ConocimientoPrueba::instantanea($this->db);
        ConocimientoPrueba::cambiar($this->carpeta, 'nodos', static function (array &$nodos): void {
            $nodos[0]['taxonomia']['especie'] = 'Felino';
        });
        try {
            $cargador->cargar($this->carpeta);
            $this->fail('No puede cambiar la especie de un código existente.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        }
    }
}
