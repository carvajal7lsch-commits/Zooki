<?php
/** A.3/B2.1: los datos ausentes permanecen desconocidos; nunca se interpretan los relatos. */
$caso = static function (array $marcas, array $paciente, string $nivel, array $tiempos = [], array $exposiciones = [], ?bool $revision = null, bool $inmediata = false, bool $contagio = false): array {
    $sintomas = [];
    foreach ($marcas as $marca) {
        $tiempo = $tiempos[$marca] ?? null;
        $sintomas['sintoma.' . $marca] = ['tiempo' => $tiempo === 'no_aplica' ? 'no_aplica' : ($tiempo === null ? 'no_se' : 'conocido'), 'minutos' => is_numeric($tiempo) ? $tiempo : null];
    }
    return ['sintomas' => $sintomas, 'exposiciones' => $exposiciones, 'paciente' => $paciente, 'esperado' => ['nivel' => $nivel, 'revision' => $revision, 'revision_inmediata' => $inmediata, 'contagio' => $contagio]];
};
$perro = ['especie' => 'especie.canino'];
$gato = ['especie' => 'especie.felino'];
return [
    'T-01' => $caso(['dificultad_respiratoria', 'encias_azuladas'], $perro + ['edad_meses' => 72], 'rojo', revision: false),
    'T-02' => $caso(['convulsion_en_curso'], $gato + ['edad_meses' => 36, 'sexo' => 'Hembra'], 'rojo', ['convulsion_en_curso' => 6], revision: false),
    'T-03' => $caso(['traumatismo', 'hemorragia_incontrolable'], $perro + ['edad_meses' => 48], 'rojo', revision: false),
    'T-04' => $caso(['intentos_orinar_minimo', 'dolor_orinar'], $gato + ['edad_meses' => 60, 'sexo' => 'Macho', 'esterilizado' => true], 'rojo', revision: false),
    'T-05' => $caso(['arcadas_improductivas', 'abdomen_distendido', 'inquietud'], $perro + ['edad_meses' => 84, 'raza_codigo' => 'raza.gran_danes'], 'rojo', revision: false),
    'T-06' => $caso(['sed_aumentada', 'decaimiento', 'secrecion_genital'], $perro + ['edad_meses' => 96, 'sexo' => 'Hembra', 'esterilizado' => false, 'dias_desde_celo' => 35], 'naranja'),
    'T-07' => $caso(['traumatismo'], $perro + ['edad_meses' => 60], 'naranja'),
    'T-08' => $caso([], $gato + ['edad_meses' => 24], 'rojo', exposiciones: ['sustancia.lilium' => ['minutos' => null]], revision: false),
    'T-09' => $caso(['respiracion_ruidosa_ejercicio', 'cansancio_ejercicio', 'exposicion_calor'], $perro + ['edad_meses' => 36, 'raza_codigo' => 'raza.bulldog_frances'], 'naranja'),
    'T-10' => $caso(['respiracion_ruidosa_ejercicio', 'cansancio_ejercicio', 'exposicion_calor'], $perro + ['edad_meses' => 36, 'raza_codigo' => 'raza.labrador'], 'amarillo'),
    'T-11' => $caso(['vomito', 'diarrea_sangre', 'no_come'], $perro + ['edad_meses' => 3, 'vacunacion' => 'incompleta'], 'naranja', contagio: true),
    'T-12' => $caso(['vomito', 'diarrea'], $perro + ['edad_meses' => 72, 'vacunacion' => 'completa', 'contexto' => ['episodios_vomito' => 1, 'activo' => true]], 'amarillo'),
    'T-13' => $caso(['no_come', 'se_esconde'], $gato + ['edad_meses' => 108, 'contexto' => ['obesidad_declarada' => true]], 'naranja', ['no_come' => 4320]),
    'T-14' => $caso([], $perro + ['contexto' => ['peso_kg' => 15]], 'naranja', exposiciones: ['sustancia.posible_xilitol' => ['minutos' => 20]], revision: true),
    'T-15' => $caso(['fiebre'], $gato, 'rojo', exposiciones: ['farmaco.paracetamol' => ['minutos' => null]], revision: false),
    'T-16' => $caso([], $perro, 'amarillo', exposiciones: ['sustancia.chocolate' => ['minutos' => 2880]], revision: true),
    'T-17' => $caso(['vomito_repetido', 'no_bebe', 'decaimiento', 'encias_secas'], $perro + ['edad_meses' => 120, 'contexto' => ['episodios_vomito' => 4]], 'naranja'),
    'T-18' => $caso(['primera_convulsion_resuelta'], $perro + ['edad_meses' => 48], 'naranja'),
    'T-19' => $caso(['convulsion_habitual_resuelta'], $perro + ['alertas_activas' => ['condicion.epilepsia'], 'contexto' => ['tratamiento_sin_identificar' => true]], 'amarillo'),
    'T-20' => $caso(['no_come', 'no_defeca'], ['especie' => 'especie.roedor', 'edad_meses' => 24, 'contexto' => ['especie_declarada' => 'Conejo', 'inicio' => 'ayer']], 'amarillo', revision: true, inmediata: true),
    'T-21' => $caso(['dificultad_respiratoria', 'plumaje_erizado'], ['especie' => 'especie.ave'], 'rojo', revision: false),
    'T-22' => $caso(['otro_no_se'], $perro, 'amarillo', revision: true),
    'T-23' => $caso(['vomito_sangre', 'decaimiento'], $gato, 'naranja'),
    'T-24' => $caso(['control_preventivo'], $perro + ['edad_meses' => 36], 'verde', ['control_preventivo' => 'no_aplica']),
    'T-25' => $caso(['picor_oidos_leve_estable'], $gato + ['edad_meses' => 48], 'verde', ['picor_oidos_leve_estable' => 10080]),
    'T-26' => $caso(['cojera_leve_estable'], $perro + ['edad_meses' => 72, 'contexto' => ['inicio' => 'hace un mes']], 'verde'),
];
