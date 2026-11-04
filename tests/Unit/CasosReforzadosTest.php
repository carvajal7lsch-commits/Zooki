<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../helpers/BuscadorSintomas.php';
require_once __DIR__ . '/../../helpers/MotorTriage.php';
require_once __DIR__ . '/../../helpers/MotorDiagnosticos.php';
require_once __DIR__ . '/../../helpers/MotorPrescripcion.php';

/** RN-209/211/416/424: banco de casos clínicos convertido a regresiones herméticas. */
final class CasosReforzadosTest extends TestCase
{
    private static function grafo(): ConocimientoClinico
    {
        static $grafo = null;
        return $grafo ??= ConocimientoRealPrueba::instantanea();
    }

    private static function fecha(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-31T10:00:00-05:00');
    }

    private static function paciente(string $especie, ?string $raza = null, ?int $edad = null, ?string $sexo = null, ?bool $esterilizado = null, ?string $vacunacion = null, ?float $celo = null, array $alertas = []): array
    {
        // Estos perfiles del banco son criollos conocidos; null en la vacuna simula ausencia de registros.
        $raza ??= ['especie.canino' => 'raza.criollo_canino', 'especie.felino' => 'raza.criollo_felino'][$especie] ?? null;
        $nodo = self::grafo()->nodos[$raza ?? ''] ?? null;
        return ['especie' => $especie, 'raza' => $nodo === null ? null : ['codigo' => $nodo['codigo'], 'atributos' => $nodo['metadatos'] ?? []], 'edad_meses' => $edad, 'sexo' => $sexo, 'esterilizado' => $esterilizado, 'vacunacion' => $vacunacion ?? 'desconocida', 'dias_desde_celo' => $celo, 'alertas_activas' => $alertas, 'exposiciones' => []];
    }

    private static function marcas(array $lista, string $prefijo = 'sintoma.'): array
    {
        $marcas = [];
        foreach ($lista as $clave => $valor) {
            $codigo = is_int($clave) ? $valor : $clave;
            $minutos = is_int($clave) ? null : $valor;
            $marcas[str_contains($codigo, '.') ? $codigo : $prefijo . $codigo] = ['tiempo' => $minutos === null ? 'no_se' : 'conocido', 'minutos' => $minutos];
        }
        return $marcas;
    }

    public static function casosTriage(): iterable
    {
        
        // Fichas típicas de una clínica en Colombia: la mayoría criollos, sin vacunas cargadas en Zooki
        $firulais = self::paciente('especie.canino', null, 36, 'Macho', true, null);            // criollo adulto, sin vacunas registradas
        $firulaisVac = self::paciente('especie.canino', null, 36, 'Macho', true, 'completa');
        $sinFecha = self::paciente('especie.canino', null, null, 'Macho', null, null);          // ficha mínima: sin nacimiento
        $cachorro = self::paciente('especie.canino', null, 3, 'Hembra', false, 'incompleta');
        $cachorroNull = self::paciente('especie.canino', null, 3, 'Hembra', false, null);
        $michi = self::paciente('especie.felino', null, 24, 'Macho', true, null);
        $michiVac = self::paciente('especie.felino', null, 24, 'Macho', true, 'completa');
        $gataSexoDesc = self::paciente('especie.felino', null, 24, null, null, null);
        $luna = self::paciente('especie.canino', 'raza.golden', 60, 'Hembra', false, 'completa', 35);
        $lunaSinCelo = self::paciente('especie.canino', 'raza.golden', 60, 'Hembra', false, 'completa', null);
        $max = self::paciente('especie.canino', 'raza.gran_danes', 48, 'Macho', true, 'completa');
        $maxCriollo = self::paciente('especie.canino', null, 48, 'Macho', true, 'completa');    // perro grande pero criollo
        $toby = self::paciente('especie.canino', 'raza.bulldog_frances', 30, 'Macho', true, 'completa');
        $conejo = self::paciente('especie.roedor', null, 12, 'Hembra', null, null);
        $loro = self::paciente('especie.ave', null, 60, null, null, null);
        $epileptico = self::paciente('especie.canino', null, 60, 'Macho', true, 'completa', null, ['condicion.epilepsia']);
        
        $maxCriollo['raza']['atributos']['tamanio'] = 'grande';
$casos = [
          // [nombre, paciente, sintomas, exposiciones, nivel esperado (criterio clínico), ¿se espera revisión?]
          ['Perro criollo vomitó una vez', $firulais, ['vomito' => 180], [], 'amarillo', false],
          ['Perro criollo vómito+diarrea, sin vacunas en Zooki', $firulais, ['vomito', 'diarrea'], [], 'amarillo', false],
          ['Perro criollo diarrea con sangre, sin vacunas en Zooki', $firulais, ['diarrea_sangre'], [], 'amarillo', false],
          ['Mismo, dueño declara vacunas completas', $firulaisVac, ['diarrea_sangre'], [], 'amarillo', false],
          ['Perro sin fecha de nacimiento, vómito', $sinFecha, ['vomito'], [], 'amarillo', true],
          ['Perro vómito + decaído', $firulais, ['vomito', 'decaimiento'], [], 'naranja', false],
          ['Cachorro 3m vómito, vac incompleta', $cachorro, ['vomito', 'diarrea_sangre', 'no_come'], [], 'naranja', false],
          ['Cachorro 3m vómito, vac sin dato', $cachorroNull, ['vomito', 'diarrea'], [], 'naranja', false],
          ['Gato vomitó, sin vacunas en Zooki', $michi, ['vomito'], [], 'amarillo', false],
          ['Gato vomitó, vacunas completas', $michiVac, ['vomito'], [], 'amarillo', false],
          ['Gato macho va a la arena y no hace', $michi, ['intentos_orinar_minimo', 'dolor_orinar'], [], 'rojo', false],
          ['Gato sexo desconocido, va a la arena y no hace', $gataSexoDesc, ['intentos_orinar_minimo', 'dolor_orinar'], [], 'naranja', true],
          ['Gato no come hace 2 días', $michiVac, ['no_come' => 2880], [], 'naranja', false],
          ['Gato no come, no sé desde cuándo', $michiVac, ['no_come'], [], 'amarillo', true],
          ['Gato comió lirio', $michiVac, [], ['lilium'], 'rojo', false],
          ['Gato le dieron paracetamol', $michiVac, [], ['farmaco.paracetamol'], 'rojo', false],
          ['Gato pipeta de perro (permetrina)', $michiVac, [], ['farmaco.permetrina'], 'rojo', false],
          ['Perro comió chocolate hace 1h', $firulaisVac, [], ['chocolate' => 60], 'naranja', true],
          ['Perro comió chocolate, no sé cuándo', $firulaisVac, [], ['chocolate'], 'naranja', true],
          ['Perro comió chocolate hace 15h', $firulaisVac, [], ['chocolate' => 900], 'amarillo', true],
          ['Perro comió uvas', $firulaisVac, [], ['uvas_pasas' => 30], 'naranja', true],
          ['Perro tomó anticongelante', $firulaisVac, [], ['etilenglicol' => 30], 'rojo', false],
          ['Perro chicle (posible xilitol)', $firulaisVac, [], ['posible_xilitol' => 20], 'naranja', true],
          ['Gran danés arcadas + barriga hinchada', $max, ['arcadas_improductivas', 'abdomen_distendido'], [], 'rojo', false],
          ['Criollo grande arcadas + barriga hinchada', $maxCriollo, ['arcadas_improductivas', 'abdomen_distendido'], [], 'rojo', false],
          ['Bulldog francés respira ruidoso al ejercicio', $toby, ['respiracion_ruidosa_ejercicio', 'cansancio_ejercicio', 'exposicion_calor'], [], 'naranja', false],
          ['Criollo respira ruidoso al ejercicio', $firulaisVac, ['respiracion_ruidosa_ejercicio'], [], 'amarillo', false],
          ['Golden hembra entera, 35 d del celo, toma mucha agua', $luna, ['sed_aumentada', 'secrecion_genital'], [], 'naranja', false],
          ['Golden hembra entera, celo sin dato, toma mucha agua', $lunaSinCelo, ['sed_aumentada', 'secrecion_genital'], [], 'amarillo', true],
          ['Perro atropellado por moto', $firulaisVac, ['traumatismo'], [], 'naranja', false],
          ['Perro convulsionando ahora', $firulaisVac, ['convulsion_en_curso'], [], 'rojo', false],
          ['Primera convulsión ya pasó', $firulaisVac, ['primera_convulsion_resuelta'], [], 'naranja', false],
          ['Epiléptico conocido, crisis habitual', $epileptico, ['convulsion_habitual_resuelta'], [], 'amarillo', false],
          ['Encías pálidas en conejo', $conejo, ['encias_palidas'], [], 'rojo', false],
          ['Conejo no come ni defeca', $conejo, ['no_come', 'no_defeca'], [], 'amarillo', true],
          ['Loro con plumas erizadas', $loro, ['plumaje_erizado'], [], 'amarillo', true],
          ['Control / vacuna anual', $firulaisVac, ['control_preventivo'], [], 'verde', false],
          ['Picor de oídos leve', $firulaisVac, ['picor_oidos_leve_estable', 'sacude_cabeza'], [], 'verde', false],
          ['Pulgas y se rasca', $firulaisVac, ['pulgas', 'picazon'], [], 'verde', false],
          ['Tos (posible tos de perreras)', $firulaisVac, ['tos', 'secrecion_nasal'], [], 'amarillo', false],
          ['Ictericia (ojos amarillos)', $michiVac, ['ictericia', 'no_come'], [], 'naranja', true],
          ['Sangra por la nariz + moretones', $firulaisVac, ['sangrado_nariz', 'moretones_piel'], [], 'naranja', false],
          ['Tres moderados: vómito, diarrea, no come', $firulaisVac, ['vomito', 'diarrea', 'no_come'], [], 'naranja', false],
          ['Otro / no sé', $firulaisVac, ['otro_no_se'], [], 'amarillo', true],
        ];
        foreach ($casos as $caso) {
            yield $caso[0] => array_slice($caso, 1);
        }
    }

    public static function casosDiagnosticos(): iterable
    {
        
        $perro = self::paciente('especie.canino', null, 36, 'Macho', true, 'completa');
        $cachorro = self::paciente('especie.canino', null, 3, 'Macho', false, 'incompleta');
        $gato = self::paciente('especie.felino', null, 36, 'Macho', true, 'completa');
        $gatoViejo = self::paciente('especie.felino', null, 156, 'Hembra', true, 'completa');
        $perraEntera = self::paciente('especie.canino', 'raza.golden', 72, 'Hembra', false, 'completa', 35);
        $granDanes = self::paciente('especie.canino', 'raza.gran_danes', 48, 'Macho', true, 'completa');
        $criolloGrande = self::paciente('especie.canino', null, 48, 'Macho', true, 'completa');
        $conejo = self::paciente('especie.roedor', null, 12, 'Hembra', null, null);
        
        $casos = [
          // [nombre, paciente, sintomas, diagnóstico que un veterinario esperaría arriba]
          ['Cachorro sin vacunas: vómito + diarrea con sangre + no come', $cachorro, ['vomito', 'diarrea_sangre', 'no_come', 'decaimiento'], 'diagnostico.parvovirosis_canina'],
          ['Perro adulto vacunado: vómito agudo', $perro, ['vomito'], 'diagnostico.gastritis_aguda'],
          ['Perro: vómito + dolor abdominal + no come', $perro, ['vomito', 'dolor_abdominal', 'no_come'], 'diagnostico.pancreatitis'],
          ['Gran danés: arcadas + abdomen distendido + inquieto', $granDanes, ['arcadas_improductivas', 'abdomen_distendido', 'inquietud'], 'diagnostico.dilatacion_torsion_gastrica'],
          ['Criollo grande: arcadas + abdomen distendido', $criolloGrande, ['arcadas_improductivas', 'abdomen_distendido'], 'diagnostico.dilatacion_torsion_gastrica'],
          ['Gato macho: intentos de orinar + dolor', $gato, ['intentos_orinar_minimo', 'dolor_orinar'], 'diagnostico.obstruccion_uretral_felina'],
          ['Gata: orina fuera de la caja + sangre en orina', $gatoViejo, ['orina_fuera_caja', 'sangre_orina', 'dolor_orinar'], 'diagnostico.cistitis_idiopatica_felina'],
          ['Perra entera 35d poscelo: sed + flujo + decaída', $perraEntera, ['sed_aumentada', 'secrecion_genital', 'decaimiento'], 'diagnostico.piometra'],
          ['Gato viejo: come mucho, pierde peso, vomita', $gatoViejo, ['hambre_aumentada', 'perdida_peso', 'vomito', 'hiperactividad'], 'diagnostico.hipertiroidismo_felino'],
          ['Perro: toma mucha agua + orina mucho + pierde peso + hambre', $perro, ['sed_aumentada', 'orina_mucho', 'perdida_peso', 'hambre_aumentada'], 'diagnostico.diabetes_mellitus'],
          ['Gato viejo: sed + orina mucho + pierde peso + mal aliento', $gatoViejo, ['sed_aumentada', 'orina_mucho', 'perdida_peso', 'mal_aliento', 'no_come'], 'diagnostico.enfermedad_renal_cronica'],
          ['Perro: tos + secreción nasal (guardería)', $perro, ['tos', 'secrecion_nasal'], 'diagnostico.tos_perreras'],
          ['Perro: sangrado nariz + moretones + fiebre + decaído (garrapatas)', $perro, ['sangrado_nariz', 'moretones_piel', 'fiebre', 'decaimiento'], 'diagnostico.ehrlichiosis_canina'],
          ['Perro: ictericia + fiebre + vómito + dolor muscular', $perro, ['ictericia', 'fiebre', 'vomito', 'dolor_muscular'], 'diagnostico.leptospirosis_canina'],
          ['Cachorro: secreción ocular/nasal + tos + tics + almohadillas duras', $cachorro, ['secrecion_ocular', 'secrecion_nasal', 'tos', 'tics_musculares', 'almohadillas_duras'], 'diagnostico.moquillo_canino'],
          ['Perro: sacude cabeza + mal olor oídos', $perro, ['sacude_cabeza', 'mal_olor_oidos', 'picor_oidos_leve_estable'], 'diagnostico.otitis_externa'],
          ['Perro: pulgas + se rasca + pierde pelo', $perro, ['pulgas', 'picazon', 'perdida_pelo'], 'diagnostico.dermatitis_alergica_pulgas'],
          ['Perro: lame patas + picazón (sin pulgas)', $perro, ['lame_patas', 'picazon'], 'diagnostico.dermatitis_atopica_canina'],
          ['Perro: picazón intensa + costras', $perro, ['picazon', 'costras_piel', 'perdida_pelo'], 'diagnostico.sarna_sarcoptica'],
          ['Gato: estornudos + secreción ocular y nasal', $gato, ['estornudos', 'secrecion_ocular', 'secrecion_nasal', 'fiebre'], 'diagnostico.complejo_respiratorio_felino'],
          ['Perro: mal aliento + encías inflamadas', $perro, ['mal_aliento', 'encias_inflamadas'], 'diagnostico.enfermedad_periodontal'],
          ['Perro: ojos rojos + secreción ocular', $perro, ['ojos_rojos', 'secrecion_ocular'], 'diagnostico.conjuntivitis'],
          ['Perro: aumento de peso + pierde pelo + decaído', $perro, ['aumento_peso', 'perdida_pelo', 'decaimiento'], 'diagnostico.hipotiroidismo_canino'],
          ['Perro viejo: cojera + rigidez', $perro, ['cojera_leve_estable', 'rigidez'], 'diagnostico.osteoartritis'],
          ['Cachorro: gusanos en heces + diarrea', $cachorro, ['gusanos_heces', 'diarrea'], 'diagnostico.ascaridiasis'],
          ['Perro: arcadas + vómito + no defeca (comió media)', $perro, ['vomito_repetido', 'no_defeca', 'dolor_abdominal', 'no_come'], 'diagnostico.cuerpo_extrano_gi'],
          ['Conejo: no come + no defeca', $conejo, ['no_come', 'no_defeca'], null],
        ];
        foreach ($casos as $caso) {
            yield $caso[0] => array_slice($caso, 1);
        }
    }

    #[DataProvider('casosTriage')]
    public function testTriageDelBanco(array $paciente, array $sintomas, array $exposiciones, string $nivel, bool $revision): void
    {
        $marcas = self::marcas($sintomas);
        $expuestas = self::marcas($exposiciones, 'sustancia.');
        $resultado = MotorTriage::calcular($marcas, $expuestas, $paciente, self::grafo(), self::fecha());
        $this->assertSame($nivel, $resultado['nivel']);
        $this->assertSame($revision, $resultado['revision'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        if ($paciente['especie'] === 'especie.felino' && $paciente['vacunacion'] === 'desconocida' && isset($marcas['sintoma.vomito'])) {
            $this->assertTrue($resultado['contagio']);
        }
    }

    #[DataProvider('casosDiagnosticos')]
    public function testRankingDelBanco(array $paciente, array $sintomas, ?string $esperado): void
    {
        $paciente['sintomas'] = self::marcas($sintomas);
        $resultado = MotorDiagnosticos::sugerir(self::grafo(), $paciente, self::fecha());
        if ($esperado !== null) {
            $this->assertSame($esperado, $resultado['candidatos'][0]['codigo'] ?? null);
        }
        $this->assertTrue($resultado['diagnostico_libre']);
    }

    public static function frases(): iterable
    {
        $frases = [
            'está vomitando' => 'sintoma.vomito',
            'tiene diarrea' => 'sintoma.diarrea',
            'tose mucho' => 'sintoma.tos',
            'orina con sangre' => 'sintoma.sangre_orina',
            'no quiere comer nada' => 'sintoma.no_come',
            'tiene los ojos amarillos' => 'sintoma.ictericia',
            'le sangra la nariz' => 'sintoma.sangrado_nariz',
            'comió chocolate' => 'sustancia.chocolate',
            'le di Dolex' => 'farmaco.paracetamol',
            'acetaminofén' => 'farmaco.paracetamol',
            'tiene churrias' => 'sintoma.diarrea',
            'está triste' => 'sintoma.decaimiento',
            'está decaído' => 'sintoma.decaimiento',
            'está aguado' => 'sintoma.decaimiento',
            'barriga hinchada' => 'sintoma.abdomen_distendido',
            'se le hinchó la barriga' => 'sintoma.abdomen_distendido',
            'panza inflada' => 'sintoma.abdomen_distendido',
            'está flaco' => 'sintoma.perdida_peso',
            'adelgazó' => 'sintoma.perdida_peso',
            'tiene mocos' => 'sintoma.secrecion_nasal',
            'tiene moquitos' => 'sintoma.secrecion_nasal',
            'le duele la patica' => 'sintoma.cojera_leve_estable',
            'cojea' => 'sintoma.cojera_leve_estable',
            'renquea' => 'sintoma.cojera_leve_estable',
            'lo mordió otro perro' => 'sintoma.traumatismo',
            'herida' => 'sintoma.traumatismo',
            'le huele feo la boca' => 'sintoma.mal_aliento',
            'aliento feo' => 'sintoma.mal_aliento',
            'mordida' => 'sintoma.traumatismo',
            'tiene garrapatas' => 'sintoma.garrapatas',
            'se comió veneno para ratas' => 'sustancia.raticida',
        ];
        foreach ($frases as $frase => $codigo) {
            yield $frase => [$frase, $codigo];
        }
    }

    #[DataProvider('frases')]
    public function testBusquedaDelDueno(string $frase, string $codigo): void
    {
        $datos = ArchivosConocimiento::leer(ConocimientoRealPrueba::CARPETA)['datos'];
        $sinonimos = [];
        foreach ($datos['sinonimos'] as $sinonimo) {
            $sinonimos[$sinonimo['nodo']][] = $sinonimo['texto'];
        }
        $catalogo = array_map(static fn (array $n): array => $n + ['sinonimos' => $sinonimos[$n['codigo']] ?? []], $datos['nodos']);
        $copia = $catalogo;
        $resultado = BuscadorSintomas::buscar($frase, $catalogo, null, ['sintoma', 'sustancia', 'farmaco']);
        $codigos = array_column($resultado, 'codigo');
        $this->assertContains($codigo, array_slice($codigos, 0, 3));
        $this->assertLessThanOrEqual(8, count($resultado));
        $this->assertSame('sintoma.otro_no_se', end($codigos));
        $this->assertSame($copia, $catalogo);
        if ($codigo === 'sintoma.sangre_orina') {
            $this->assertNotContains('sintoma.vomito_sangre', $codigos);
        }
    }

    public function testExposicionDesconocidaNoModificaEntradasNiSuponeTiempoDeSintomas(): void
    {
        $paciente = self::paciente('especie.canino', edad: 36, sexo: 'Macho', esterilizado: true, vacunacion: 'completa');
        $exposiciones = self::marcas(['chocolate'], 'sustancia.');
        $copia = $exposiciones;
        $resultado = MotorTriage::calcular([], $exposiciones, $paciente, self::grafo(), self::fecha());
        $this->assertSame('naranja', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
        $this->assertSame($copia, $exposiciones);
        $this->assertNotEmpty($resultado['datos_faltantes']);
        $gato = self::paciente('especie.felino', edad: 36, sexo: 'Macho', vacunacion: 'completa');
        $resultado = MotorTriage::calcular(self::marcas(['no_come']), [], $gato, self::grafo(), self::fecha());
        $this->assertSame('amarillo', $resultado['nivel']);
        $this->assertTrue($resultado['revision']);
    }

    public static function seguridad(): iterable
    {
        yield 'Collie ivermectina' => ['raza.collie', 'farmaco.ivermectina', true, false];
        yield 'Australiano ivermectina' => ['raza.pastor_australiano', 'farmaco.ivermectina', true, false];
        yield 'Shetland ivermectina' => ['raza.pastor_shetland', 'farmaco.ivermectina', true, false];
        yield 'Border ivermectina' => ['raza.border_collie', 'farmaco.ivermectina', false, true];
        yield 'Pastor alemán ivermectina' => ['raza.pastor_aleman', 'farmaco.ivermectina', false, true];
        yield 'Criollo ivermectina' => ['raza.criollo_canino', 'farmaco.ivermectina', false, false];
        foreach (['collie', 'pastor_australiano', 'pastor_shetland', 'border_collie', 'pastor_aleman'] as $raza) {
            yield $raza . ' loperamida' => ['raza.' . $raza, 'farmaco.loperamida', false, true];
        }
        foreach (['collie', 'pastor_australiano', 'pastor_shetland', 'border_collie'] as $raza) {
            yield $raza . ' milbemicina' => ['raza.' . $raza, 'farmaco.milbemicina', false, true];
        }
        yield 'Criollo milbemicina' => ['raza.criollo_canino', 'farmaco.milbemicina', false, false];
    }

    #[DataProvider('seguridad')]
    public function testSeguridadMdr1(string $raza, string $farmaco, bool $bloqueada, bool $advertida): void
    {
        $resultado = MotorPrescripcion::evaluar($farmaco, self::paciente('especie.canino', $raza, 36), [], [], [], self::grafo(), self::fecha());
        $this->assertSame($bloqueada, $resultado['bloqueada']);
        $this->assertSame($advertida, $resultado['advertencias'] !== []);
        $this->assertFalse($resultado['evaluacion_incompleta']);
        if ($bloqueada) {
            $this->assertNotEmpty(PrescripcionPublica::desde($resultado, self::grafo())['motivos'][0]['fuentes']);
        }
    }

    public function testPrednisolonaAdvierteEnAmbosSentidosConCadaAine(): void
    {
        $paciente = self::paciente('especie.canino', edad: 36);
        foreach (['meloxicam', 'carprofeno', 'aspirina', 'ibuprofeno'] as $aine) {
            foreach ([['farmaco.prednisolona', 'farmaco.' . $aine], ['farmaco.' . $aine, 'farmaco.prednisolona']] as [$farmaco, $otro]) {
                $resultado = MotorPrescripcion::evaluar($farmaco, $paciente, [], [['farmaco' => $otro, 'visible' => true]], [], self::grafo(), self::fecha());
                $this->assertContains('interaccion', array_column($resultado['advertencias'], 'tipo'));
            }
        }
    }

    public static function precargas(): iterable
    {
        yield 'osteoartritis' => ['osteoartritis', ['meloxicam'], ['carprofeno']];
        yield 'atopia' => ['dermatitis_atopica_canina', ['oclacitinib'], ['prednisolona']];
        yield 'sarna' => ['sarna_sarcoptica', ['selamectina'], ['milbemicina', 'ivermectina']];
        yield 'giardia' => ['giardiasis', ['fenbendazol'], ['metronidazol']];
        yield 'ascaridos' => ['ascaridiasis', ['pirantel'], ['fenbendazol']];
        yield 'dermatofitosis' => ['dermatofitosis', ['itraconazol'], ['terbinafina']];
        yield 'pancreatitis' => ['pancreatitis', ['maropitant', 'buprenorfina'], ['ondansetron']];
        yield 'gastritis' => ['gastritis_aguda', ['maropitant'], ['metoclopramida']];
        yield 'cistitis' => ['cistitis_bacteriana', ['trimetoprim_sulfa'], ['amoxicilina_clavulanato', 'cefalexina', 'enrofloxacina']];
        yield 'periodontal' => ['enfermedad_periodontal', ['amoxicilina_clavulanato'], []];
    }

    #[DataProvider('precargas')]
    public function testPrecargaYAlternativas(string $diagnostico, array $esperadas, array $alternativas): void
    {
        $paciente = self::paciente('especie.canino', edad: 48, sexo: 'Macho', esterilizado: true, vacunacion: 'completa');
        $resultado = MotorPrescripcion::sugerir('diagnostico.' . $diagnostico, 20, $paciente, [], [], self::grafo(), self::fecha());
        $precargadas = array_filter($resultado['tratamientos'], static fn (array $f): bool => $f['precargar']);
        $otras = array_filter($resultado['tratamientos'], static fn (array $f): bool => !$f['precargar']);
        $codigos = static fn (array $nombres): array => array_map(static fn (string $n): string => 'farmaco.' . $n, $nombres);
        $this->assertEqualsCanonicalizing($codigos($esperadas), array_column($precargadas, 'farmaco'));
        $this->assertEqualsCanonicalizing($codigos($alternativas), array_column($otras, 'farmaco'));
    }

    public function testCollieExcluyeIvermectinaYDuplicadoNoPrecargaOtroAine(): void
    {
        $collie = self::paciente('especie.canino', 'raza.collie', 48);
        $resultado = MotorPrescripcion::sugerir('diagnostico.sarna_sarcoptica', 20, $collie, [], [], self::grafo(), self::fecha());
        $this->assertNotContains('farmaco.ivermectina', array_column($resultado['tratamientos'], 'farmaco'));
        $descartada = array_column($resultado['descartadas'], null, 'farmaco')['farmaco.ivermectina'];
        $this->assertFalse($descartada['precargar']);
        $this->assertTrue($descartada['seguridad']['bloqueada']);
        $this->assertSame('Collie', $descartada['seguridad']['motivos'][0]['paciente']);
        $this->assertNotEmpty($descartada['seguridad']['motivos'][0]['fuentes']);
        $vigentes = [['farmaco' => 'farmaco.meloxicam', 'visible' => true]];
        $resultado = MotorPrescripcion::sugerir('diagnostico.osteoartritis', 20, $collie, [], $vigentes, self::grafo(), self::fecha());
        $this->assertSame([], array_values(array_filter($resultado['tratamientos'], static fn (array $f): bool => $f['precargar'])));
        $meloxicam = array_column($resultado['tratamientos'], null, 'farmaco')['farmaco.meloxicam'];
        $this->assertContains('duplicado', array_column($meloxicam['seguridad']['advertencias'], 'tipo'));
        $interna = MotorPrescripcion::evaluar('farmaco.meloxicam', $collie, [], [['farmaco' => 'farmaco.meloxicam', 'visible' => false, 'id_clinica' => 999]], [], self::grafo(), self::fecha());
        $publica = PrescripcionPublica::desde($interna, self::grafo());
        $this->assertTrue($publica['requiere_conciliacion']);
        $this->assertSame(['tipo', 'mensaje'], array_keys($publica['advertencias'][0]));
        $this->assertStringNotContainsString('999', json_encode($publica));
    }

    public function testFelineEnrofloxacinaYMetamizolSoloAdviertenConFuente(): void
    {
        foreach (['enrofloxacina', 'metamizol'] as $farmaco) {
            $resultado = MotorPrescripcion::evaluar('farmaco.' . $farmaco, self::paciente('especie.felino', edad: 48), [], [], [], self::grafo(), self::fecha());
            $this->assertFalse($resultado['bloqueada']);
            $this->assertNotEmpty($resultado['advertencias'][0]['fuentes']);
        }
    }

    public function testGruposDePautaSonCerradosYEleccionNecesitaClase(): void
    {
        $datos = ArchivosConocimiento::leer(ConocimientoRealPrueba::CARPETA)['datos'];
        $indice = array_key_first(array_filter($datos['aristas'], static fn (array $a): bool => isset($a['pauta'])));
        foreach ([['grupo' => 'inventado'], ['grupo' => null], ['grupo' => 'aine', 'grupo_eleccion' => 'inventado'], ['grupo_eleccion' => 'control_prurito']] as $cambio) {
            $copia = $datos;
            unset($copia['aristas'][$indice]['pauta']['grupo'], $copia['aristas'][$indice]['pauta']['grupo_eleccion']);
            $copia['aristas'][$indice]['pauta'] += $cambio;
            try {
                ValidadorConocimiento::validar($copia);
                $this->fail('Aceptó un grupo fuera del contrato.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('aristas.json', $error->getMessage());
            }
        }
        foreach ($datos['aristas'] as $arista) {
            if (isset($arista['pauta'])) {
                $this->assertContains($arista['pauta']['grupo'], ValidadorConocimiento::GRUPOS_PAUTA);
            }
        }
    }

    public function testRaticidaEnAmbasEspeciesYGarrapatasCaninas(): void
    {
        foreach (['especie.canino', 'especie.felino'] as $especie) {
            $paciente = self::paciente($especie, edad: 48, sexo: 'Macho', esterilizado: true, vacunacion: 'completa');
            $resultado = MotorTriage::calcular([], self::marcas(['raticida' => 30], 'sustancia.'), $paciente, self::grafo(), self::fecha());
            $this->assertSame('naranja', $resultado['nivel']);
            $this->assertTrue($resultado['revision']);
        }
        $paciente = self::paciente('especie.canino', edad: 48, sexo: 'Macho', esterilizado: true, vacunacion: 'completa');
        $paciente['sintomas'] = self::marcas(['garrapatas']);
        $resultado = MotorTriage::calcular($paciente['sintomas'], [], $paciente, self::grafo(), self::fecha());
        $this->assertSame('verde', $resultado['nivel']);
        $diagnosticos = MotorDiagnosticos::sugerir(self::grafo(), $paciente, self::fecha());
        $this->assertSame('diagnostico.ehrlichiosis_canina', $diagnosticos['candidatos'][0]['codigo']);
    }
}
