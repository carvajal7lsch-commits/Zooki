<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/BuscadorSintomas.php';

final class BuscadorSintomasTest extends TestCase
{
    public static function catalogo(): array
    {
        $nodos = json_decode(file_get_contents(__DIR__ . '/../../database/conocimiento/nodos.json'), true, 64, JSON_THROW_ON_ERROR);
        $sinonimos = json_decode(file_get_contents(__DIR__ . '/../../database/conocimiento/sinonimos.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($nodos as &$nodo) {
            $nodo['sinonimos'] = array_column(array_filter($sinonimos, static fn (array $sinonimo): bool => $sinonimo['nodo'] === $nodo['codigo']), 'texto');
        }
        unset($nodo);
        return $nodos;
    }

    public function testBomitaSugiereOpcionesSinMarcarNiInventarSangre(): void
    {
        $catalogo = self::catalogo();
        $copia = $catalogo;
        $resultado = BuscadorSintomas::buscar('bomita', $catalogo);
        $codigos = array_column($resultado, 'codigo');
        $this->assertContains('sintoma.vomito', $codigos);
        $this->assertContains('sintoma.vomito_sangre', $codigos);
        $this->assertContains('sintoma.otro_no_se', $codigos);
        $this->assertSame($copia, $catalogo);
        foreach ($resultado as $opcion) {
            $this->assertEqualsCanonicalizing(['codigo', 'etiqueta', 'similitud', 'coincidencia'], array_keys($opcion));
        }
    }

    public function testNormalizaTildesMayusculasYOrdenaExactoAntesDePrefijoYAproximado(): void
    {
        $catalogo = self::catalogo();
        $a = BuscadorSintomas::buscar('VÓMITO', $catalogo);
        $this->assertSame($a, BuscadorSintomas::buscar('  vómito  ', $catalogo));
        $this->assertSame('exacta', $a[0]['coincidencia']);
        $this->assertSame('sintoma.vomito', $a[0]['codigo']);
        $this->assertContains('sintoma.decaimiento', array_column(BuscadorSintomas::buscar('desaido', $catalogo), 'codigo'));
        $this->assertContains('sintoma.vomito_sangre', array_column(BuscadorSintomas::buscar('sangre vomito', $catalogo), 'codigo'));
        $this->assertContains('sintoma.vomito', array_column(BuscadorSintomas::buscar('vmoito', $catalogo), 'codigo'));
    }

    public function testDamerauOperaSobreUnicodeYAdmiteTransposicionCompleta(): void
    {
        $this->assertSame(1, BuscadorSintomas::distancia('ñá', 'áñ'));
        $this->assertSame(2, BuscadorSintomas::distancia('ca', 'abc'));
        $this->assertSame(0, BuscadorSintomas::distancia('', ''));
    }

    public function testFiltroMantieneAlarmasYOtroYRespetaLimiteYActivos(): void
    {
        $catalogo = [
            ['codigo' => 'sintoma.solo_perro', 'tipo' => 'sintoma', 'etiqueta' => 'Alarma de perro', 'especies' => ['especie.canino']],
            ['codigo' => 'sintoma.universal', 'tipo' => 'sintoma', 'etiqueta' => 'Alarma universal', 'especies' => ['especie.canino'], 'alarma_universal' => true],
            ['codigo' => 'sintoma.retirado', 'tipo' => 'sintoma', 'etiqueta' => 'Alarma retirada', 'activo' => false],
            ['codigo' => 'sintoma.otro_no_se', 'tipo' => 'sintoma', 'etiqueta' => 'Otro / no sé'],
        ];
        $resultado = BuscadorSintomas::buscar('alarma', $catalogo, 'especie.felino');
        $this->assertSame(['sintoma.universal', 'sintoma.otro_no_se'], array_column($resultado, 'codigo'));
        $this->assertCount(1, BuscadorSintomas::buscar('xyzxxxxxxxx', $catalogo));
        $this->assertLessThanOrEqual(BuscadorSintomas::MAX_RESULTADOS, count(BuscadorSintomas::buscar('', self::catalogo())));
    }
}
