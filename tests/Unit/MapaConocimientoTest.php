<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/MapaConocimiento.php';

/** M2 C6, HU-2.12: armado puro de la respuesta del mapa, sin base de datos. */
final class MapaConocimientoTest extends TestCase
{
    private const FUENTE = '[{"codigo":"msd.x","entidad":"MSD","titulo":"Página","url":"https://www.msdvetmanual.com/x","fecha_consulta":"2026-10-14","seccion":"S","localizador":"Apartado","fundamento":"clinico"}]';

    private static function nodo(string $codigo, string $tipo, array $extra = []): array
    {
        return $extra + [
            'codigo' => $codigo, 'tipo' => $tipo, 'etiqueta' => ucfirst($codigo), 'nivel_triage' => null, 'alarma_universal' => 0,
            'moderado' => 0, 'metadatos' => null, 'fuentes' => self::FUENTE, 'estado_validacion' => 'pendiente', 'id_especie' => null, 'especie_raza' => null,
        ];
    }

    private static function construir(array $reglas = []): array
    {
        $nodos = [
            self::nodo('especie.canino', 'especie', ['id_especie' => 1, 'metadatos' => '{"cobertura":{"triage":"parcial","diagnosticos":"parcial"}}']),
            self::nodo('especie.ave', 'especie', ['id_especie' => 4]),
            self::nodo('raza.gran_danes', 'raza', ['especie_raza' => 1, 'metadatos' => '{"tamanio":"gigante"}']),
            self::nodo('sintoma.vomito', 'sintoma', ['nivel_triage' => 'amarillo', 'moderado' => 1]),
            self::nodo('sintoma.colapso', 'sintoma', ['nivel_triage' => 'rojo', 'alarma_universal' => 1]),
            self::nodo('diagnostico.gastritis', 'diagnostico'),
        ];
        $aristas = [[
            'codigo' => 'arista.sugiere', 'origen' => 'sintoma.vomito', 'destino' => 'diagnostico.gastritis', 'tipo_relacion' => 'sugiere',
            'signo' => 'positivo', 'peso' => '0.600', 'efecto' => 'evidencia', 'condiciones' => '{"predicado":"especie","codigo":"especie.canino"}',
            'fuentes' => self::FUENTE, 'estado_validacion' => 'pendiente',
        ]];
        $sinonimos = [['nodo' => 'sintoma.vomito', 'texto' => 'bomita']];
        return MapaConocimiento::construir($nodos, $aristas, $sinonimos, $reglas, str_repeat('a', 64), 'Pendiente de validación veterinaria.');
    }

    public function testReglasSeAsocianALosNodosQueNombranYNoSonAristas(): void
    {
        $regla = [
            'codigo' => 'triage.prueba', 'motor' => 'triage', 'fuentes' => self::FUENTE, 'estado_validacion' => 'pendiente',
            'condiciones' => '{"todos":[{"predicado":"especie","codigo":"especie.canino"},{"alguno":[{"predicado":"sintoma_marcado","codigo":"sintoma.vomito"},{"predicado":"raza","atributo":"tamanio","valor":"gigante"}]}]}',
            'resultado' => '{"nivel":"naranja"}',
        ];
        $ranking = [
            'codigo' => 'ranking.prueba', 'motor' => 'diagnosticos', 'fuentes' => self::FUENTE, 'estado_validacion' => 'pendiente',
            'condiciones' => '{"predicado":"especie","codigo":"especie.canino"}', 'resultado' => '{"diagnostico":"diagnostico.gastritis","peso":0.3}',
        ];
        $mapa = self::construir([$regla, $ranking]);
        $porCodigo = array_column($mapa['nodes'], null, 'codigo');
        $this->assertSame(['triage.prueba', 'ranking.prueba'], $porCodigo['especie.canino']['reglas']);
        $this->assertSame(['triage.prueba'], $porCodigo['sintoma.vomito']['reglas']);
        $this->assertSame(['ranking.prueba'], $porCodigo['diagnostico.gastritis']['reglas']);
        $this->assertSame([], $porCodigo['sintoma.colapso']['reglas']);
        $this->assertSame(['especie.canino', 'sintoma.vomito'], $mapa['reglas'][0]['nodos']);
        $this->assertSame(['especie.canino', 'diagnostico.gastritis'], $mapa['reglas'][1]['nodos']);
        $this->assertCount(1, $mapa['edges'], 'Una regla no se convierte en arista.');
        $this->assertSame('naranja', $mapa['reglas'][0]['resultado']['nivel']);
    }

    public function testEspeciesCoberturaVersionYConteos(): void
    {
        $mapa = self::construir();
        $porCodigo = array_column($mapa['nodes'], null, 'codigo');
        $this->assertSame(['especie.canino'], $porCodigo['raza.gran_danes']['especies']);
        $this->assertSame(['especie.canino'], $porCodigo['especie.canino']['especies']);
        $this->assertSame([], $porCodigo['sintoma.colapso']['especies']);
        $this->assertTrue($porCodigo['sintoma.colapso']['alarma_universal']);
        $this->assertSame(['bomita'], $porCodigo['sintoma.vomito']['sinonimos']);
        $this->assertSame(['codigo' => 'especie.canino', 'etiqueta' => 'Especie.canino', 'diagnosticos' => 'parcial', 'prescripcion' => 'sin_cobertura', 'triage' => 'parcial'], $mapa['cobertura'][0]);
        $this->assertSame('sin_cobertura', $mapa['cobertura'][1]['triage'], 'Sin declaración no hay cobertura.');
        $this->assertSame(['hash' => str_repeat('a', 64), 'corta' => str_repeat('a', 12)], $mapa['version']);
        $this->assertSame(['nodos' => 6, 'aristas' => 1, 'reglas' => 0, 'sinonimos' => 1, 'por_tipo' => ['diagnostico' => 1, 'especie' => 2, 'raza' => 1, 'sintoma' => 2]], $mapa['conteos']);
        $this->assertSame(7, $mapa['pendientes']);
        $this->assertFalse($mapa['vacio']);
        $this->assertSame(0.6, $mapa['edges'][0]['peso']);
        $this->assertSame(['predicado' => 'especie', 'codigo' => 'especie.canino'], $mapa['edges'][0]['condiciones']);
    }

    public function testFuentesSoloConCamposBibliograficosYEnlaceHttps(): void
    {
        $mapa = self::construir();
        $this->assertSame(['entidad' => 'MSD', 'titulo' => 'Página', 'url' => 'https://www.msdvetmanual.com/x', 'localizador' => 'Apartado', 'fundamento' => 'clinico', 'fecha_consulta' => '2026-10-14'], $mapa['nodes'][0]['fuentes'][0]);
        $inseguro = MapaConocimiento::construir([self::nodo('sintoma.x', 'sintoma', ['fuentes' => '[{"titulo":"T","url":"javascript:alert(1)"}]'])], [], [], [], null, 'L');
        $this->assertNull($inseguro['nodes'][0]['fuentes'][0]['url']);
        $this->assertNull($inseguro['version']);
    }

    public function testRespuestaSinClavesDePacientesNiIdsInternos(): void
    {
        $json = json_encode(self::construir());
        foreach (['id_mascota', 'id_consulta', 'id_cita', 'id_clinica', 'id_tratamiento', 'id_propietario', 'id_usuario', 'id_nodo', 'id_arista', 'id_especie', 'especie_raza'] as $clave) {
            $this->assertStringNotContainsString('"' . $clave . '"', $json);
        }
    }

    public function testConjuntoVacioSinCoberturaFalsa(): void
    {
        $mapa = MapaConocimiento::construir([], [], [], [], null, 'L');
        $this->assertTrue($mapa['vacio']);
        $this->assertSame([], $mapa['cobertura']);
        $this->assertSame(0, $mapa['conteos']['nodos']);
    }
}
