<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ArchivosConocimiento.php';

/** M2 B2: el conocimiento real de database/conocimiento, leído de los archivos y sin motor (RN-209, RN-211, RN-212, RN-416 a RN-418, RN-424). */
final class ConocimientoRealTest extends TestCase
{
    private const CARPETA = __DIR__ . '/../../database/conocimiento';

    /** Tabla del Anexo B2 del plan M2: códigos que exige cada fila. */
    private const TABLA_B2 = [
        'T-01' => ['sintoma.dificultad_respiratoria', 'sintoma.encias_azuladas'],
        'T-02' => ['sintoma.convulsion_en_curso'],
        'T-03' => ['sintoma.traumatismo', 'sintoma.hemorragia_incontrolable'],
        'T-04' => ['sintoma.intentos_orinar_minimo', 'sintoma.dolor_orinar', 'especie.felino', 'triage.urinario_macho'],
        'T-05' => ['sintoma.arcadas_improductivas', 'sintoma.abdomen_distendido', 'sintoma.inquietud', 'raza.gran_danes', 'triage.abdomen_gigante'],
        'T-06' => ['sintoma.sed_aumentada', 'sintoma.decaimiento', 'sintoma.secrecion_genital', 'especie.canino', 'triage.reproductivo_poscelo'],
        'T-07' => ['sintoma.traumatismo'],
        'T-08' => ['sustancia.lilium', 'especie.felino', 'triage.lilium_felino'],
        'T-09' => ['sintoma.respiracion_ruidosa_ejercicio', 'sintoma.cansancio_ejercicio', 'sintoma.exposicion_calor', 'raza.bulldog_frances', 'triage.ejercicio_braquicefalo'],
        'T-10' => ['sintoma.respiracion_ruidosa_ejercicio', 'sintoma.cansancio_ejercicio', 'sintoma.exposicion_calor', 'raza.labrador'],
        'T-11' => ['sintoma.vomito', 'sintoma.diarrea_sangre', 'sintoma.no_come', 'especie.canino', 'triage.digestivo_cachorro', 'triage.contagio_digestivo'],
        'T-12' => ['sintoma.vomito', 'sintoma.diarrea', 'especie.canino'],
        'T-13' => ['sintoma.no_come', 'sintoma.se_esconde', 'especie.felino', 'triage.anorexia_felina_sostenida'],
        'T-14' => ['sustancia.posible_xilitol', 'especie.canino', 'triage.posible_xilitol'],
        'T-15' => ['farmaco.paracetamol', 'especie.felino', 'sintoma.fiebre', 'triage.paracetamol_felino'],
        'T-16' => ['sustancia.chocolate', 'especie.canino', 'triage.chocolate_tardio'],
        'T-17' => ['sintoma.vomito_repetido', 'sintoma.no_bebe', 'sintoma.decaimiento', 'sintoma.encias_secas', 'triage.digestivo_compromiso'],
        'T-18' => ['sintoma.primera_convulsion_resuelta'],
        'T-19' => ['sintoma.convulsion_habitual_resuelta', 'condicion.epilepsia', 'triage.crisis_habitual_documentada'],
        'T-20' => ['sintoma.no_come', 'sintoma.no_defeca', 'especie.roedor', 'triage.apetito_sin_heces'],
        'T-21' => ['sintoma.dificultad_respiratoria', 'sintoma.plumaje_erizado', 'especie.ave'],
        'T-22' => ['sintoma.otro_no_se', 'triage.reserva_otro'],
        'T-23' => ['sintoma.vomito_sangre', 'sintoma.decaimiento', 'triage.digestivo_compromiso'],
        'T-24' => ['sintoma.control_preventivo', 'triage.preventivo'],
        'T-25' => ['sintoma.picor_oidos_leve_estable', 'triage.oidos_estable'],
        'T-26' => ['sintoma.cojera_leve_estable', 'triage.cojera_estable'],
        'P1 encías pálidas' => ['sintoma.encias_palidas'],
        'P1 colapso' => ['sintoma.colapso'],
        'P1 no poder orinar' => ['sintoma.imposibilidad_orinar'],
        'P1 golpe de calor' => ['sintoma.golpe_calor'],
        'P1 parto' => ['sintoma.parto_esfuerzo_sin_cria'],
        'Parto menor de 30 minutos' => ['sintoma.parto_esfuerzo_menos_30', 'triage.parto_menos_30'],
    ];

    /** Lo que escribiría o dictaría el propietario para cada marca de A.3; debe encontrarse por etiqueta o sinónimo. */
    private const BUSQUEDAS_A3 = [
        'sintoma.dificultad_respiratoria' => ['Dificultad para respirar', 'respira con la boca abierta', 'mueve mucho la cola al respirar'],
        'sintoma.encias_azuladas' => ['encías moradas'],
        'sintoma.encias_palidas' => ['encías blancas'],
        'sintoma.convulsion_en_curso' => ['está convulsionando'],
        'sintoma.colapso' => ['se desmayó'],
        'sintoma.hemorragia_incontrolable' => ['sangra mucho', 'no para de sangrar'],
        'sintoma.imposibilidad_orinar' => ['no puede orinar'],
        'sintoma.golpe_calor' => ['insolación'],
        'sintoma.parto_esfuerzo_sin_cria' => ['puja y no sale el perrito'],
        'sintoma.parto_esfuerzo_menos_30' => ['puja hace menos de media hora sin cría', 'puja y no sale el gatito'],
        'sintoma.traumatismo' => ['lo atropelló una moto', 'atropellado'],
        'sintoma.intentos_orinar_minimo' => ['va mucho a la arena y no hace'],
        'sintoma.dolor_orinar' => ['maúlla al orinar'],
        'sintoma.arcadas_improductivas' => ['intenta vomitar y no le sale nada'],
        'sintoma.abdomen_distendido' => ['barriga hinchada'],
        'sintoma.inquietud' => ['inquieto'],
        'sintoma.sed_aumentada' => ['toma mucha agua'],
        'sintoma.decaimiento' => ['decaída', 'desaido', 'esta desaido', 'aguado'],
        'sintoma.secrecion_genital' => ['le sale flujo'],
        'sintoma.respiracion_ruidosa_ejercicio' => ['respira muy ruidoso'],
        'sintoma.cansancio_ejercicio' => ['muy cansado'],
        'sintoma.exposicion_calor' => ['día caluroso'],
        'sintoma.vomito' => ['VÓMITO', 'vomitó', 'bomita', 'trasboca'],
        'sintoma.vomito_repetido' => ['vomitó varias veces'],
        'sintoma.vomito_sangre' => ['bomita sangre', 'Vómito con sangre'],
        'sintoma.diarrea' => ['Diarrea'],
        'sintoma.diarrea_sangre' => ['diarrea con sangre', 'popó con sangre'],
        'sintoma.no_come' => ['no quiere comer', 'no ha comido'],
        'sintoma.no_bebe' => ['no toma agua'],
        'sintoma.encias_secas' => ['encías secas'],
        'sintoma.se_esconde' => ['Se esconde o se aísla', 'se aísla'],
        'sintoma.fiebre' => ['fiebre'],
        'sintoma.primera_convulsion_resuelta' => ['convulsionó por primera vez'],
        'sintoma.convulsion_habitual_resuelta' => ['convulsión como las de siempre'],
        'sintoma.no_defeca' => ['no ha hecho popó'],
        'sintoma.plumaje_erizado' => ['esponjado'],
        'sintoma.otro_no_se' => ['Otro / no sé', 'está raro'],
        'sintoma.control_preventivo' => ['control anual', 'vacuna'],
        'sintoma.picor_oidos_leve_estable' => ['se rasca las orejas'],
        'sintoma.cojera_leve_estable' => ['cojea un poco'],
        'sustancia.lilium' => ['lirios'],
        'sustancia.posible_xilitol' => ['chicle sin azúcar'],
        'sustancia.chocolate' => ['chocolate', 'chocolatina'],
        'farmaco.paracetamol' => ['acetaminofén', 'acetaminofen'],
    ];

    private static array $datos;

    public static function setUpBeforeClass(): void
    {
        self::$datos = ArchivosConocimiento::leer(self::CARPETA)['datos'];
    }

    public function testElConjuntoRealPasaElValidadorYNoEstaVacio(): void
    {
        $lectura = ArchivosConocimiento::leer(self::CARPETA);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $lectura['version']);
        foreach (['fuentes', 'nodos', 'aristas', 'sinonimos', 'reglas'] as $grupo) {
            $this->assertNotEmpty($lectura['datos'][$grupo], $grupo);
        }
    }

    public function testCadaFilaDeLaTablaB2TieneTodosSusCodigos(): void
    {
        $codigos = [...array_column(self::$datos['nodos'], 'codigo'), ...array_column(self::$datos['reglas'], 'codigo')];
        foreach (self::TABLA_B2 as $fila => $necesarios) {
            foreach ($necesarios as $codigo) {
                $this->assertContains($codigo, $codigos, $fila . ' necesita ' . $codigo);
            }
        }
    }

    public function testNucleoComunDePerrosYGatos(): void
    {
        $tipos = array_count_values(array_column(self::$datos['nodos'], 'tipo'));
        $this->assertGreaterThanOrEqual(15, $tipos['diagnostico']);
        $this->assertGreaterThanOrEqual(15, $tipos['farmaco'] + $tipos['sustancia']);
        $conSintomas = [];
        foreach (self::$datos['aristas'] as $arista) {
            if ($arista['tipo_relacion'] === 'sugiere') {
                $conSintomas[$arista['destino']] = true;
            }
        }
        foreach (self::$datos['nodos'] as $nodo) {
            if ($nodo['tipo'] === 'diagnostico') {
                $this->assertArrayHasKey($nodo['codigo'], $conSintomas, 'Diagnóstico sin aristas síntoma→diagnóstico');
            }
        }
        $interacciones = array_filter(self::$datos['aristas'], static fn (array $a): bool => $a['tipo_relacion'] === 'interactua_con');
        $this->assertNotEmpty($interacciones);
        foreach ($interacciones as $arista) {
            $this->assertSame('advertencia', $arista['efecto'], $arista['codigo']);
            $this->assertContains('clinico', array_column($arista['fuentes'], 'fundamento'), $arista['codigo']);
        }
    }

    public function testNingunBloqueoSinFuenteClinicaDeToxicidad(): void
    {
        $bloqueos = 0;
        foreach (self::$datos['aristas'] as $arista) {
            if ($arista['efecto'] !== 'bloqueo') {
                continue;
            }
            $bloqueos++;
            $this->assertSame('toxico_para', $arista['tipo_relacion'], $arista['codigo']);
            $this->assertContains('clinico', array_column($arista['fuentes'], 'fundamento'), $arista['codigo']);
        }
        foreach (self::$datos['reglas'] as $regla) {
            $this->assertNotSame('bloqueo', $regla['resultado']['efecto'] ?? null, $regla['codigo']);
        }
        $this->assertGreaterThan(0, $bloqueos);
    }

    public function testNingunElementoNombraUnCasoYLasReglasUsanAtributosDeRaza(): void
    {
        foreach (['nodos', 'aristas', 'sinonimos', 'reglas'] as $grupo) {
            $texto = json_encode(self::$datos[$grupo], JSON_UNESCAPED_UNICODE);
            $this->assertDoesNotMatchRegularExpression('/\bT-\d{2}\b/i', $texto, $grupo);
        }
        $reglas = json_encode(self::$datos['reglas'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('"raza.', $reglas, 'Una regla usa atributos de raza, no razas concretas.');
    }

    public function testNingunaReglaRebajaNiDejaVerdeUnaAlarma(): void
    {
        $alarmas = [];
        foreach (self::$datos['nodos'] as $nodo) {
            if ($nodo['alarma_universal'] ?? false) {
                $alarmas[] = $nodo['codigo'];
            }
        }
        $this->assertCount(9, $alarmas, 'P1: ocho señales originales más la marca de parto prolongado o tiempo desconocido.');
        foreach (self::$datos['reglas'] as $regla) {
            if (($regla['resultado']['nivel'] ?? null) !== 'verde') {
                continue;
            }
            $condiciones = json_encode($regla['condiciones']);
            foreach ($alarmas as $alarma) {
                $this->assertStringNotContainsString($alarma, $condiciones, $regla['codigo']);
            }
        }
    }

    public function testFuentesConUrlHttpsEspecificaYFechaDeConsulta(): void
    {
        $usadas = [];
        foreach (['nodos', 'aristas', 'sinonimos', 'reglas'] as $grupo) {
            foreach (self::$datos[$grupo] as $elemento) {
                $this->assertSame('pendiente', $elemento['estado_validacion'], $elemento['codigo']);
                foreach ($elemento['fuentes'] as $referencia) {
                    $usadas[$referencia['codigo']] = true;
                    $this->assertNotSame('', trim($referencia['localizador']));
                }
            }
        }
        foreach (self::$datos['fuentes'] as $fuente) {
            $this->assertStringStartsWith('https://', $fuente['url'], $fuente['codigo']);
            $ruta = parse_url($fuente['url'], PHP_URL_PATH) ?? '';
            $this->assertGreaterThan(1, strlen(trim($ruta, '/')), 'Portada no basta: ' . $fuente['codigo']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fuente['fecha_consulta']);
            $this->assertArrayHasKey($fuente['codigo'], $usadas, 'Fuente sin uso: ' . $fuente['codigo']);
        }
    }

    public function testSinDosisNiPautasDeTratamiento(): void
    {
        // El manifiesto declara la limitación «sin dosis»; se revisan los elementos.
        foreach (['fuentes', 'nodos', 'aristas', 'sinonimos', 'reglas'] as $grupo) {
            $texto = json_encode(self::$datos[$grupo], JSON_UNESCAPED_UNICODE);
            $this->assertSame(0, preg_match('/\d\s*(mg|ml|UI|U)\s*(\/|por\b)/iu', $texto), $grupo . ': unidad de dosis');
            $this->assertSame(0, preg_match('/\b(dosis|dose|dosage|posología)\b/iu', $texto), $grupo . ': menciona dosis');
        }
        $this->assertNotContains('trata', array_column(self::$datos['aristas'], 'tipo_relacion'));
    }

    public function testCoberturaPorEspecie(): void
    {
        $esperada = ['especie.canino' => 'parcial', 'especie.felino' => 'parcial', 'especie.roedor' => 'sin_cobertura', 'especie.ave' => 'sin_cobertura', 'especie.reptil' => 'sin_cobertura', 'especie.exotico' => 'sin_cobertura'];
        $especies = array_filter(self::$datos['nodos'], static fn (array $n): bool => $n['tipo'] === 'especie');
        $this->assertCount(count($esperada), $especies);
        foreach ($especies as $nodo) {
            foreach (['diagnosticos', 'prescripcion', 'triage'] as $motor) {
                $this->assertSame($esperada[$nodo['codigo']], $nodo['metadatos']['cobertura'][$motor], $nodo['codigo'] . ' ' . $motor);
            }
        }
    }

    public function testRazasYEspeciesExistenEnLaSemilla(): void
    {
        $semilla = file_get_contents(__DIR__ . '/../../database/02_semilla.sql');
        foreach (self::$datos['nodos'] as $nodo) {
            if (isset($nodo['taxonomia'])) {
                foreach ($nodo['taxonomia'] as $nombre) {
                    $this->assertStringContainsString("'" . $nombre . "')", $semilla, $nodo['codigo']);
                }
            }
        }
    }

    public function testCadaSintomaDeA3SeEncuentraPorEtiquetaOSinonimo(): void
    {
        $indice = [];
        foreach (self::$datos['nodos'] as $nodo) {
            $indice[ArchivosConocimiento::normalizarTexto($nodo['etiqueta'])][] = $nodo['codigo'];
        }
        foreach (self::$datos['sinonimos'] as $sinonimo) {
            $indice[ArchivosConocimiento::normalizarTexto($sinonimo['texto'])][] = $sinonimo['nodo'];
        }
        $a3 = [];
        foreach (self::TABLA_B2 as $necesarios) {
            foreach ($necesarios as $codigo) {
                if (preg_match('/^(sintoma|sustancia|farmaco)\./', $codigo)) {
                    $a3[$codigo] = true;
                }
            }
        }
        $this->assertSame([], array_values(array_diff(array_keys($a3), array_keys(self::BUSQUEDAS_A3))), 'Marca de A.3 sin búsqueda de prueba');
        foreach (self::BUSQUEDAS_A3 as $codigo => $busquedas) {
            foreach ($busquedas as $busqueda) {
                $this->assertContains($codigo, $indice[ArchivosConocimiento::normalizarTexto($busqueda)] ?? [], '«' . $busqueda . '» no encuentra ' . $codigo);
            }
        }
    }

    public function testCadaToxicoDeEspecieTieneMinimoNaranjaConSuFuente(): void
    {
        $reglas = array_column(self::$datos['reglas'], null, 'codigo');
        foreach (['canino', 'felino'] as $especie) {
            $regla = $reglas['triage.toxicos_' . $especie];
            $this->assertSame('triage', $regla['motor']);
            $this->assertSame(['nivel' => 'naranja', 'revision' => true], $regla['resultado']);
            $this->assertSame(['predicado' => 'especie', 'codigo' => 'especie.' . $especie], $regla['condiciones']['todos'][0]);
            $this->assertCount(2, $regla['condiciones']['todos']);
            $opciones = $regla['condiciones']['todos'][1]['alguno'];
            $toxicos = array_filter(self::$datos['aristas'], static fn (array $a): bool => $a['tipo_relacion'] === 'toxico_para' && $a['origen'] === 'especie.' . $especie);
            $this->assertEqualsCanonicalizing(array_column($toxicos, 'destino'), array_column($opciones, 'codigo'));
            foreach ($toxicos as $arista) {
                $opcion = array_values(array_filter($opciones, static fn (array $o): bool => $o['codigo'] === $arista['destino']))[0];
                $esperada = $arista['destino'] === 'sustancia.chocolate'
                    ? ['predicado' => 'minutos_desde_exposicion', 'codigo' => 'sustancia.chocolate', 'maximo' => 720, 'incluye_maximo' => false]
                    : ['predicado' => 'exposicion', 'codigo' => $arista['destino']];
                $this->assertSame($esperada, $opcion, $arista['codigo']);
                foreach ($arista['fuentes'] as $fuente) {
                    if ($fuente['fundamento'] === 'clinico') {
                        $this->assertContains($fuente, $regla['fuentes'], $arista['codigo']);
                    }
                }
            }
            $this->assertContains('politica', array_column($regla['fuentes'], 'fundamento'));
        }
    }

    public function testEtilenglicolRojoEnAmbasEspeciesYLiriosYParacetamolConservanRojo(): void
    {
        $reglas = array_column(self::$datos['reglas'], null, 'codigo');
        $etilenglicol = $reglas['triage.etilenglicol'];
        $this->assertSame(['todos' => [
            ['alguno' => [['predicado' => 'especie', 'codigo' => 'especie.canino'], ['predicado' => 'especie', 'codigo' => 'especie.felino']]],
            ['predicado' => 'exposicion', 'codigo' => 'sustancia.etilenglicol'],
        ]], $etilenglicol['condiciones']);
        $this->assertSame('rojo', $etilenglicol['resultado']['nivel']);
        $this->assertContains('clinico', array_column($etilenglicol['fuentes'], 'fundamento'));
        $this->assertContains('politica', array_column($etilenglicol['fuentes'], 'fundamento'));
        foreach (['triage.lilium_felino' => 'sustancia.lilium', 'triage.paracetamol_felino' => 'farmaco.paracetamol'] as $codigo => $sustancia) {
            $this->assertSame('rojo', $reglas[$codigo]['resultado']['nivel']);
            $this->assertSame(['todos' => [
                ['predicado' => 'especie', 'codigo' => 'especie.felino'],
                ['predicado' => 'exposicion', 'codigo' => $sustancia],
            ]], $reglas[$codigo]['condiciones']);
        }
    }

    public function testChocolateNoTieneCandidatoSuperiorAAmarilloCon720MinutosOMas(): void
    {
        $reglas = array_column(self::$datos['reglas'], null, 'codigo');
        $this->assertSame(['nivel' => 'amarillo', 'revision' => true], $reglas['triage.chocolate_tardio']['resultado']);
        $this->assertSame(['predicado' => 'minutos_desde_exposicion', 'codigo' => 'sustancia.chocolate', 'minimo' => 720, 'incluye_minimo' => true], $reglas['triage.chocolate_tardio']['condiciones']['todos'][2]);
        // Se inspecciona la declaración completa, sin adelantar el evaluador de C2/C4.
        foreach (self::$datos['reglas'] as $regla) {
            if (!in_array($regla['resultado']['nivel'] ?? null, ['naranja', 'rojo'], true)) {
                continue;
            }
            $pendientes = [$regla['condiciones']];
            while ($pendientes !== []) {
                $condicion = array_pop($pendientes);
                if (($condicion['codigo'] ?? null) === 'sustancia.chocolate') {
                    $this->assertSame(['predicado' => 'minutos_desde_exposicion', 'codigo' => 'sustancia.chocolate', 'maximo' => 720, 'incluye_maximo' => false], $condicion, $regla['codigo']);
                }
                array_push($pendientes, ...($condicion['todos'] ?? $condicion['alguno'] ?? []));
            }
        }
    }

    public function testAbdomenCaninoNaranjaNoExigeTamanioYGiganteConservaRojo(): void
    {
        $reglas = array_column(self::$datos['reglas'], null, 'codigo');
        $this->assertSame(['todos' => [
            ['predicado' => 'especie', 'codigo' => 'especie.canino'],
            ['predicado' => 'sintoma_marcado', 'codigo' => 'sintoma.arcadas_improductivas'],
            ['predicado' => 'sintoma_marcado', 'codigo' => 'sintoma.abdomen_distendido'],
        ]], $reglas['triage.abdomen_canino']['condiciones']);
        $this->assertSame('naranja', $reglas['triage.abdomen_canino']['resultado']['nivel']);
        $this->assertSame('rojo', $reglas['triage.abdomen_gigante']['resultado']['nivel']);
        $this->assertSame(['alguno' => [
            ['predicado' => 'raza', 'atributo' => 'tamanio', 'valor' => 'gigante'],
            ['predicado' => 'raza', 'atributo' => 'tamanio', 'valor' => 'grande'],
        ]], $reglas['triage.abdomen_gigante']['condiciones']['todos'][3]);
    }

    public function testPartoTieneDosMarcasYTiempoDesconocidoEsAlarmaUniversal(): void
    {
        $nodos = array_column(self::$datos['nodos'], null, 'codigo');
        $roja = $nodos['sintoma.parto_esfuerzo_sin_cria'];
        $breve = $nodos['sintoma.parto_esfuerzo_menos_30'];
        $this->assertSame('Parto con esfuerzo sin cría: más de 30 minutos o no sé', $roja['etiqueta']);
        $this->assertTrue($roja['alarma_universal']);
        $this->assertSame('rojo', $roja['nivel_triage']);
        $this->assertSame('Parto con esfuerzo sin cría: menos de 30 minutos', $breve['etiqueta']);
        $this->assertSame('naranja', $breve['nivel_triage']);
        $this->assertFalse($breve['alarma_universal'] ?? false);
        $reglas = array_column(self::$datos['reglas'], null, 'codigo');
        $this->assertArrayNotHasKey('triage.parto_prolongado', $reglas);
        $this->assertSame(['predicado' => 'sintoma_marcado', 'codigo' => $breve['codigo']], $reglas['triage.parto_menos_30']['condiciones']);
        $this->assertSame(['nivel' => 'naranja', 'revision' => true], $reglas['triage.parto_menos_30']['resultado']);
    }
}
