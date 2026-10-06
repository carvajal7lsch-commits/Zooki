<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/DocsIndex.php';

/**
 * Registros del buscador de la documentación (specs/buscador-documentacion.md, BD-3 y BD-4).
 */
class DocsIndexTest extends TestCase
{
    /** BD-4: la fila que abre con el código lo define; la que solo lo cita, no. */
    public function testLosCodigosDefinidosSonLosQueAbrenUnaFila(): void
    {
        $cuerpo = "| ID | Regla |\n|---|---|\n"
            . "| RN-411 | Triage de 4 niveles. |\n"
            . "| **RN-G02** | Un propietario solo ve sus mascotas. |\n"
            . "| RF-4.7 | Priorizar citas (RN-401). |\n"
            . "El nivel lo define el Grafo I (RN-412).\n";

        $this->assertSame(['RN-411', 'RN-G02', 'RF-4.7'], DocsIndex::codigosDefinidos($cuerpo));
    }

    public function testElEncabezadoDefineSuCodigoConPuntos(): void
    {
        $this->assertSame(['HU-4.13'], DocsIndex::codigosDefinidos('Texto sin tabla.', 'HU-4.13 — Agendar cita'));
        $this->assertSame(['RE-0.1.1'], DocsIndex::codigosDefinidos("| RE-0.1.1 | Formulario. |\n"));
        $this->assertSame([], DocsIndex::codigosDefinidos('Ver RN-411.', 'Módulo 4 — Agenda'));
        // Módulos con letra: T (transversal) y F (requisitos futuros).
        $this->assertSame(['HU-T.16'], DocsIndex::codigosDefinidos('Texto.', 'HU-T.16 — Expiración de la sesión'));
        $this->assertSame(['RF-T.1', 'RE-T.13.4', 'RF-F.2'], DocsIndex::codigosDefinidos("| RF-T.1 | Google. |\n| RE-T.13.4 | Bloqueo. |\n| RF-F.2 | WhatsApp. |\n"));
    }

    /** BD-3: ningún trozo pasa el tope y juntos reconstruyen la sección entera. */
    public function testFragmentarNoPierdeTextoNiPasaElTope(): void
    {
        $lineas = [];
        for ($i = 1; $i <= 300; $i++) {
            $lineas[] = "| RF-{$i} | Requisito número {$i} con tildes: acción, atención. |\n";
        }
        $cuerpo = implode('', $lineas);

        $trozos = DocsIndex::fragmentar($cuerpo, 1000);

        $this->assertGreaterThan(1, count($trozos));
        foreach ($trozos as $trozo) {
            $this->assertLessThanOrEqual(1000, strlen($trozo));
            $this->assertStringEndsWith("\n", $trozo, 'Se corta entre líneas, no a mitad de una fila.');
        }
        $this->assertSame($cuerpo, implode('', $trozos));
    }

    public function testUnaLineaMasLargaQueElTopeSeCortaSinRomperCaracteres(): void
    {
        $linea = str_repeat('atención ', 300);

        $trozos = DocsIndex::fragmentar($linea, 1000);

        foreach ($trozos as $trozo) {
            $this->assertLessThanOrEqual(1000, strlen($trozo));
            $this->assertTrue(mb_check_encoding($trozo, 'UTF-8'));
        }
        $this->assertSame($linea, implode('', $trozos));
    }

    public function testLosBloquesDeCodigoNoSeIndexan(): void
    {
        $trozos = DocsIndex::fragmentar("Antes\n```php\n\$secreto = 1;\n```\nDespués\n", 1000);

        $this->assertCount(1, $trozos);
        $this->assertStringNotContainsString('secreto', $trozos[0]);
        $this->assertStringContainsString('Después', $trozos[0]);
    }

    public function testUnaSeccionSinCuerpoDaUnTrozoVacio(): void
    {
        $this->assertSame([''], DocsIndex::fragmentar('', 1000));
    }
}
