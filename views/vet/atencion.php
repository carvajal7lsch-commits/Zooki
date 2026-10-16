<?php
// views/vet/atencion.php — Pantalla de atención de una cita.
//
// Solo estructura (ZOOKI_REGLAS §1): los datos llegan ya calculados desde
// CitaController::atencion(), los estilos están en css/atencion.css y el
// comportamiento en js/atencion.js (ambos se cargan desde el layout).
$e = fn($valor) => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
$fechaCorta = fn($fecha) => empty($fecha) ? '—' : date('d/m/Y', strtotime($fecha));

$estadoLabel = [
    'pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'en_curso' => 'En curso',
    'completada' => 'Completada', 'cancelada' => 'Cancelada', 'no_asistio' => 'No asistió',
    'sin_cerrar' => 'Sin cerrar',
];
$estadoActual = strtolower($cita['estado'] ?? 'pendiente');
$consultaRegistrada = !empty($consultaCita);
// RN-409: una atención que quedó sin cerrar se sigue documentando igual.
$enCurso = in_array($estadoActual, ['en_curso', 'sin_cerrar'], true);
$abierta = in_array($estadoActual, ['pendiente', 'confirmada'], true);
// RN-407: se inicia el día de la cita desde 15 minutos antes de su hora
// ($esDiaDeLaCita viene del controlador con esa regla ya aplicada).
$porIniciar = $abierta && $esDiaDeLaCita;
$pesoReferencia = $resumenClinico['ultimo_peso'] ?? ($mascota['peso'] ?? null);
$hoyIso = date('Y-m-d');
// M2 C5a: límites y avisos de ValidadorClinico, los mismos del servidor (js/consulta-formulario.js).
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
$avisoConsulta = fn(string $campo): string => $e(ValidadorClinico::AVISOS_CONSULTA[$campo]);
// M2 C5b y C7: un peso NULL dice «Sin peso registrado» (no se pudo pesar o no se anotó), nunca 0.
$textoPeso = fn($peso): string => $peso === null || $peso === '' ? 'Sin peso registrado' : $e($peso) . ' kg';
$claseCampo = 'consulta-campo';
$claseEtiqueta = 'consulta-campo__etiqueta';
$prefijoConsulta = 'atencion';
// M2 C8 (H5): la foto si existe; si no, el avatar azul con la inicial, como en el portal.
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
$tieneFoto = !empty($mascota['url_foto']);
$ayudaSigno = fn(string $campo): string => $e(ValidadorClinico::ayudaSigno($campo));
$obligatorio = '<span class="consulta-obligatorio" aria-hidden="true">*</span>';
?>
<section class="atencion" id="atencionApp"
         data-id-cita="<?= (int) $cita['id_cita'] ?>"
         data-id-mascota="<?= (int) $mascota['id_mascota'] ?>"
         data-estado="<?= $e($estadoActual) ?>"
         data-consulta-registrada="<?= $consultaRegistrada ? '1' : '0' ?>">

    <!-- ── Barra superior ─────────────────────────────────────────── -->
    <header class="atencion__barra">
        <a href="index.php?action=vet_agenda" class="atencion__volver" title="Volver a la agenda">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="atencion__titulo">
            <h1>Atención médica</h1>
            <p>
                Cita #<?= (int) $cita['id_cita'] ?> · <?= $fechaCorta($cita['fecha']) ?> · <?= $e(substr($cita['hora'], 0, 5)) ?>
                <?php if ($tipoCitaNombre !== ''): ?> · <?= $e($tipoCitaNombre) ?><?php endif; ?>
            </p>
        </div>
        <span class="atencion__estado estado--<?= $e($estadoActual) ?>" id="atencionEstado"><?= $e($estadoLabel[$estadoActual] ?? $estadoActual) ?></span>
    </header>

    <div class="atencion__cuerpo">

        <!-- ── Ficha del paciente (fija) ──────────────────────────── -->
        <?php // M2 C8 (H4): más compacta; los datos en una cuadrícula densa y el resto en filas cortas. ?>
        <aside class="atencion__paciente" data-paciente-compacto>
            <div class="paciente__cabecera">
                <?php if ($tieneFoto): ?>
                    <img src="<?= $e($fotoMascota) ?>" alt="" class="paciente__foto" id="atencionFoto">
                <?php else: ?>
                    <span class="avatar-iniciales paciente__avatar" aria-hidden="true"><?= $e(PresentacionPlataforma::iniciales((string) ($mascota['nombre'] ?? '?'))) ?></span>
                <?php endif; ?>
                <div class="paciente__identidad">
                    <h2><?= $e($mascota['nombre'] ?? 'Sin nombre') ?></h2>
                    <p><?= $e($mascota['nombre_especie'] ?? '') ?><?= !empty($mascota['nombre_raza']) ? ' · ' . $e($mascota['nombre_raza']) : '' ?><?php // HU-15: raza que el propietario escribió porque no estaba en la lista ?><?= !empty($mascota['raza_indicada']) ? ' · el propietario indicó «' . $e($mascota['raza_indicada']) . '»' : '' ?></p>
                </div>
            </div>

            <p class="paciente-compacto__datos"><?= $e($edadMascota) ?> · <?= $textoPeso($pesoReferencia) ?> · <?= $e($mascota['numero_historia_clinica'] ?: 'Historia nueva') ?></p>
            <dl class="paciente__datos">
                <div><dt>Edad</dt><dd><?= $e($edadMascota) ?></dd></div>
                <div><dt>Sexo</dt><dd><?= $e($mascota['sexo'] ?? '—') ?></dd></div>
                <div><dt>Peso</dt><dd><?= $pesoReferencia !== null && $pesoReferencia !== '' ? $e($pesoReferencia) . ' kg' : '—' ?></dd></div>
                <div><dt>Historia</dt><dd><?= $e($mascota['numero_historia_clinica'] ?: 'Nueva') ?></dd></div>
            </dl>

            <div class="paciente-compacto__controles">
                <button type="button" class="paciente-compacto__alertas" data-paciente-alertas aria-expanded="false" aria-controls="atencionAlertas">Cargando alertas</button>
                <button type="button" class="paciente-compacto__ficha" data-paciente-ficha aria-expanded="false" aria-controls="atencionFicha">Ver ficha <i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            </div>
            <div class="paciente-compacto__lista" id="atencionAlertas">
            <?php // M2 C1 (RE-2.1.5): alergias y alertas de todas las clínicas, a la vista durante la atención. ?>
            <section class="alertas-medicas alertas-medicas--compacta" aria-label="Alergias y alertas" data-alertas-medicas data-cargar data-id-mascota="<?= (int) $mascota['id_mascota'] ?>" data-nombre-mascota="<?= $e($mascota['nombre'] ?? '') ?>"></section>

            </div>
            <dl class="paciente__resumen paciente-compacto__ficha-contenido" id="atencionFicha">
                <div class="paciente__bloque">
                    <dt class="paciente__etiqueta">Propietario</dt>
                    <dd class="paciente__texto">
                        <?= $e($propietario['nombre_completo'] ?? '—') ?>
                        <?php if (!empty($propietario['telefono'])): ?>
                            <a class="paciente__telefono" href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $propietario['telefono'])) ?>">
                                <i class="fas fa-phone" aria-hidden="true"></i> <?= $e($propietario['telefono']) ?>
                            </a>
                        <?php endif; ?>
                    </dd>
                </div>

                <div class="paciente__bloque">
                    <dt class="paciente__etiqueta">Motivo de la cita</dt>
                    <dd class="paciente__texto"><?= $e($cita['motivo'] ?: 'Sin motivo indicado') ?></dd>
                </div>

                <div class="paciente__bloque">
                    <dt class="paciente__etiqueta">Pendientes</dt>
                    <dd>
                        <?php if (empty($resumenClinico['alertas'])): ?>
                            <p class="paciente__ok"><i class="fas fa-check-circle" aria-hidden="true"></i> Vacunas y desparasitación al día</p>
                        <?php else: ?>
                            <ul class="paciente__alertas">
                                <?php foreach ($resumenClinico['alertas'] as $alerta): ?>
                                    <li class="alerta alerta--<?= $e($alerta['estado']) ?>">
                                        <i class="fas <?= $alerta['clase'] === 'vacuna' ? 'fa-syringe' : 'fa-shield-alt' ?>" aria-hidden="true"></i>
                                        <span><?= $e($alerta['clase'] === 'vacuna' ? $alerta['nombre'] : 'Desparasitación ' . $alerta['nombre']) ?></span>
                                        <small><?= $alerta['estado'] === 'vencida' ? 'Vencida ' : 'Vence ' ?><?= $fechaCorta($alerta['fecha']) ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </dd>
                </div>

                <?php if (!empty($resumenClinico['ultima_consulta'])): ?>
                    <div class="paciente__bloque">
                        <dt class="paciente__etiqueta">Última consulta · <?= $fechaCorta($resumenClinico['ultima_consulta']['fecha']) ?></dt>
                        <dd class="paciente__texto paciente__texto--recortado"><?= $e($resumenClinico['ultima_consulta']['diagnostico']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </aside>

        <!-- ── Panel de atención ──────────────────────────────────── -->
        <main class="atencion__panel">
            <nav class="atencion__tabs" role="tablist">
                <button type="button" class="atencion__tab is-active" data-tab="consulta" role="tab" aria-selected="true">
                    <i class="fas fa-stethoscope"></i> Consulta
                </button>
                <button type="button" class="atencion__tab" data-tab="vacuna" role="tab" aria-selected="false">
                    <i class="fas fa-syringe"></i> Vacuna <span class="atencion__contador" data-contador="vacuna"><?= count($vacunas) ?></span>
                </button>
                <button type="button" class="atencion__tab" data-tab="desparasitacion" role="tab" aria-selected="false">
                    <i class="fas fa-shield-alt"></i> Desparasitación <span class="atencion__contador" data-contador="desparasitacion"><?= count($desparasitaciones) ?></span>
                </button>
                <button type="button" class="atencion__tab" data-tab="historial" role="tab" aria-selected="false">
                    <i class="fas fa-history"></i> Historial <span class="atencion__contador"><?= count($consultas) ?></span>
                </button>
            </nav>

            <div class="atencion__contenido">

                <?php if ($porIniciar): ?>
                    <div class="atencion__aviso" id="avisoIniciar">
                        <i class="fas fa-info-circle"></i>
                        <span>La atención de esta cita aún no ha iniciado.</span>
                        <button type="button" class="atencion__btn atencion__btn--primario" id="btnIniciarAtencion">
                            <i class="fas fa-play"></i> Iniciar atención
                        </button>
                    </div>
                <?php elseif ($abierta): ?>
                    <div class="atencion__aviso">
                        <i class="fas fa-calendar-day"></i>
                        <span>
                            <?= $cita['fecha'] < $hoyIso
                                ? 'Esta cita ya pasó sin atenderse. Si el paciente no vino, márcala como «No asistió» desde el calendario.'
                                : 'Esta cita es del ' . $fechaCorta($cita['fecha']) . ': la atención solo se puede iniciar ese día.' ?>
                        </span>
                    </div>
                <?php elseif ($estadoActual === 'no_asistio'): ?>
                    <div class="atencion__aviso">
                        <i class="fas fa-user-slash"></i>
                        <span>El paciente no asistió a esta cita.</span>
                    </div>
                <?php elseif ($estadoActual === 'completada'): ?>
                    <div class="atencion__aviso atencion__aviso--ok">
                        <i class="fas fa-check-circle"></i>
                        <span>Esta atención ya se finalizó.</span>
                    </div>
                <?php elseif ($estadoActual === 'cancelada'): ?>
                    <div class="atencion__aviso atencion__aviso--error">
                        <i class="fas fa-ban"></i>
                        <span>Esta cita fue cancelada: no se puede atender.</span>
                    </div>
                <?php endif; ?>

                <!-- CONSULTA -->
                <section class="atencion__vista is-active" data-vista="consulta">
                    <?php if ($consultaRegistrada): ?>
                        <div class="consulta-resumen">
                            <div class="consulta-resumen__signos">
                                <div><span>Peso</span><strong><?= $textoPeso($consultaCita['peso'] ?? null) ?></strong></div>
                                <div><span>Temperatura</span><strong><?= $e($consultaCita['temperatura'] ?? '—') ?> °C</strong></div>
                                <div><span>F. cardíaca</span><strong><?= $e($consultaCita['frecuencia_cardiaca'] ?? '—') ?> lpm</strong></div>
                                <div><span>F. respiratoria</span><strong><?= $e($consultaCita['frecuencia_respiratoria'] ?? '—') ?> rpm</strong></div>
                            </div>
                            <div class="consulta-resumen__grid">
                                <div><span>Motivo</span><p><?= nl2br($e($consultaCita['motivo_consulta'])) ?></p></div>
                                <div><span>Anamnesis</span><p><?= nl2br($e($consultaCita['anamnesis'] ?: '—')) ?></p></div>
                                <div><span>Diagnóstico</span><p><?= nl2br($e($consultaCita['diagnostico'])) ?></p></div>
                                <div><span>Plan</span><p><?= nl2br($e($consultaCita['plan_tratamiento'] ?: '—')) ?></p></div>
                            </div>
                            <?php if (!empty($tratamientosCita)): ?>
                                <ul class="consulta-resumen__tratamientos">
                                    <?php foreach ($tratamientosCita as $t): ?>
                                        <li><i class="fas fa-pills"></i> <strong><?= $e($t['medicamento']) ?></strong> · <?= $e($t['dosis']) ?> · <?= $e($t['via_administracion']) ?> · <?= $e($t['duracion']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php elseif (in_array($estadoActual, ['completada', 'cancelada', 'no_asistio'], true)): ?>
                        <p class="atencion__vacio">
                            <?= [
                                'completada' => 'Esta cita se completó sin una consulta registrada.',
                                'cancelada' => 'La cita se canceló antes de registrar una consulta.',
                                'no_asistio' => 'El paciente no asistió: no hay consulta que registrar.',
                            ][$estadoActual] ?>
                        </p>
                    <?php else: ?>
                        <form id="formConsultaAtencion" class="consulta-form consulta-formulario" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="id_mascota" value="<?= (int) $mascota['id_mascota'] ?>">
                            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

                            <ol class="atencion__pasos">
                                <li><button type="button" class="atencion__paso-btn is-active" data-paso="0"><span>1</span> Motivo</button></li>
                                <li><button type="button" class="atencion__paso-btn" data-paso="1"><span>2</span> Signos vitales</button></li>
                                <li><button type="button" class="atencion__paso-btn" data-paso="2"><span>3</span> Diagnóstico y plan</button></li>
                                <li><button type="button" class="atencion__paso-btn" data-paso="3"><span>4</span> Tratamientos</button></li>
                                <li><button type="button" class="atencion__paso-btn" data-paso="4"><span>5</span> Adjuntos</button></li>
                            </ol>

                            <fieldset class="atencion__fieldset" data-requiere-inicio <?= $enCurso ? '' : 'disabled' ?>>
                                <div class="atencion__paso is-active">
                                    <div class="consulta-campo">
                                        <label for="atencionMotivo" class="consulta-campo__etiqueta">Motivo de la consulta <?= $obligatorio ?></label>
                                        <input type="text" id="atencionMotivo" name="motivo" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" data-etiqueta="el motivo" value="<?= $e($cita['motivo'] ?? '') ?>"
                                               data-consulta-campo="motivo" data-aviso-requerido="<?= $avisoConsulta('motivo') ?>" placeholder="Por qué viene hoy">
                                        <small class="cuenta-validacion" data-campo-aviso="motivo"></small>
                                    </div>
                                    <?php include __DIR__ . '/../partials/consulta_sintomas.php'; ?>
                                    <div class="consulta-campo consulta-campo--crece">
                                        <label for="atencionAnamnesis" class="consulta-campo__etiqueta">Anamnesis</label>
                                        <textarea id="atencionAnamnesis" name="anamnesis" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" placeholder="Historia relatada por el propietario, síntomas, desde cuándo…"></textarea>
                                    </div>
                                </div>

                                <div class="atencion__paso">
                                    <?php // M2 C8 (H8): restricción de entrada y ayuda de ValidadorClinico, igual que en el modal. ?>
                                    <div class="consulta-signos">
                                        <div class="consulta-signo">
                                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-weight"></i></span>
                                            <div class="consulta-signo__cuerpo">
                                                <label for="atencionPeso" class="consulta-campo__etiqueta">Peso <?= $obligatorio ?></label>
                                                <div class="consulta-signo__entrada">
                                                    <input id="atencionPeso" name="peso" <?= ValidadorClinico::atributosSigno('peso') ?> required aria-required="true" data-consulta-campo="peso" data-aviso-rango="<?= $avisoConsulta('peso') ?>" data-aviso-requerido="<?= $e(ValidadorClinico::AVISO_PESO_REQUERIDO) ?>" data-etiqueta="el peso" placeholder="<?= $pesoReferencia !== null ? $e($pesoReferencia) : '0.00' ?>">
                                                    <span class="consulta-signo__unidad">kg</span>
                                                </div>
                                                <small class="consulta-ayuda"><?= $ayudaSigno('peso') ?><?= $pesoReferencia !== null ? ' · último: ' . $e($pesoReferencia) . ' kg' : '' ?></small>
                                                <?php // Decisión del usuario (2026-10-15): sin peso aproximado; la casilla deja el peso en NULL. ?>
                                                <label class="consulta-sin-peso"><input type="checkbox" name="peso_desconocido" value="1" data-sin-peso="peso" id="atencionSinPeso"> <span>No se pudo pesar</span></label>
                                                <small class="cuenta-validacion" data-campo-aviso="peso"></small>
                                            </div>
                                        </div>
                                        <div class="consulta-signo">
                                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-thermometer-half"></i></span>
                                            <div class="consulta-signo__cuerpo">
                                                <label for="atencionTemperatura" class="consulta-campo__etiqueta">Temperatura</label>
                                                <div class="consulta-signo__entrada">
                                                    <input id="atencionTemperatura" name="temperatura" <?= ValidadorClinico::atributosSigno('temperatura') ?> data-consulta-campo="temperatura" data-aviso-rango="<?= $avisoConsulta('temperatura') ?>" data-etiqueta="la temperatura" placeholder="38.5">
                                                    <span class="consulta-signo__unidad">°C</span>
                                                </div>
                                                <small class="consulta-ayuda"><?= $ayudaSigno('temperatura') ?></small>
                                                <small class="cuenta-validacion" data-campo-aviso="temperatura"></small>
                                            </div>
                                        </div>
                                        <div class="consulta-signo">
                                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
                                            <div class="consulta-signo__cuerpo">
                                                <label for="atencionFrecuenciaCardiaca" class="consulta-campo__etiqueta">Frecuencia cardíaca</label>
                                                <div class="consulta-signo__entrada">
                                                    <input id="atencionFrecuenciaCardiaca" name="frecuencia_cardiaca" <?= ValidadorClinico::atributosSigno('frecuencia_cardiaca') ?> data-consulta-campo="frecuencia_cardiaca" data-aviso-rango="<?= $avisoConsulta('frecuencia_cardiaca') ?>" data-etiqueta="la frecuencia cardíaca" placeholder="100">
                                                    <span class="consulta-signo__unidad">lpm</span>
                                                </div>
                                                <small class="consulta-ayuda"><?= $ayudaSigno('frecuencia_cardiaca') ?></small>
                                                <small class="cuenta-validacion" data-campo-aviso="frecuencia_cardiaca"></small>
                                            </div>
                                        </div>
                                        <div class="consulta-signo">
                                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-lungs"></i></span>
                                            <div class="consulta-signo__cuerpo">
                                                <label for="atencionFrecuenciaRespiratoria" class="consulta-campo__etiqueta">Frecuencia respiratoria</label>
                                                <div class="consulta-signo__entrada">
                                                    <input id="atencionFrecuenciaRespiratoria" name="frecuencia_respiratoria" <?= ValidadorClinico::atributosSigno('frecuencia_respiratoria') ?> data-consulta-campo="frecuencia_respiratoria" data-aviso-rango="<?= $avisoConsulta('frecuencia_respiratoria') ?>" data-etiqueta="la frecuencia respiratoria" placeholder="24">
                                                    <span class="consulta-signo__unidad">rpm</span>
                                                </div>
                                                <small class="consulta-ayuda"><?= $ayudaSigno('frecuencia_respiratoria') ?></small>
                                                <small class="cuenta-validacion" data-campo-aviso="frecuencia_respiratoria"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="atencion__paso">
                                    <?php // M2 C8 (H7): el paso es el único que desplaza; las etiquetas no se montan entre sí. ?>
                                    <?php include __DIR__ . '/../partials/consulta_diagnosticos.php'; ?>
                                    <div class="consulta-campo">
                                        <label for="atencionDiagnostico" class="consulta-campo__etiqueta">Diagnóstico <?= $obligatorio ?></label>
                                        <textarea id="atencionDiagnostico" name="diagnostico" rows="3" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" data-etiqueta="el diagnóstico"
                                                  data-consulta-campo="diagnostico" data-aviso-requerido="<?= $avisoConsulta('diagnostico') ?>"></textarea>
                                        <small class="cuenta-validacion" data-campo-aviso="diagnostico"></small>
                                    </div>
                                    <div class="consulta-fila-2">
                                        <div class="consulta-campo">
                                            <label for="atencionPlan" class="consulta-campo__etiqueta">Plan / recomendaciones</label>
                                            <textarea id="atencionPlan" name="plan_tratamiento" rows="3" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                                        </div>
                                        <div class="consulta-campo">
                                            <label for="atencionObservaciones" class="consulta-campo__etiqueta">Observaciones</label>
                                            <textarea id="atencionObservaciones" name="observaciones" rows="3" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="atencion__paso" data-tratamientos data-avisos="<?= $e(json_encode(ValidadorTratamiento::AVISOS, JSON_UNESCAPED_UNICODE)) ?>">
                                    <?php // M2 C5b: alergias y alertas de la mascota a la vista antes de prescribir (C1). ?>
                                    <section class="alertas-medicas alertas-medicas--compacta" aria-label="Alergias y alertas" data-alertas-medicas data-cargar data-id-mascota="<?= (int) $mascota['id_mascota'] ?>" data-nombre-mascota="<?= $e($mascota['nombre'] ?? '') ?>"></section>
                                    <div class="consulta-seccion__cabecera">
                                        <h4 class="consulta-seccion__titulo">Tratamientos</h4>
                                        <button type="button" class="consulta-boton consulta-boton--suave" id="btnAgregarTratamiento" data-agregar-tratamiento>
                                            <i class="fas fa-plus" aria-hidden="true"></i> Agregar medicamento
                                        </button>
                                    </div>
                                    <p class="consulta-tratamientos__vacio" id="tratamientosVacio" data-tratamientos-vacio>Sin medicamentos. Agrega uno si hace falta.</p>
                                    <div id="treatmentsList" data-tratamientos-lista></div>
                                    <?php include __DIR__ . '/../partials/consulta_tratamiento_fila.php'; ?>
                                </div>

                                <div class="atencion__paso">
                                    <label class="consulta-adjuntos file-upload-zone">
                                        <input type="file" name="archivos[]" id="archivosConsulta" multiple accept=".jpg,.jpeg,.png,.pdf">
                                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                        <span>Arrastra o elige radiografías, exámenes o fotos</span>
                                        <small class="consulta-ayuda">JPG, PNG o PDF · máximo 10 MB cada uno</small>
                                        <div class="file-list"></div>
                                    </label>
                                </div>
                            </fieldset>
                        </form>
                    <?php endif; ?>
                </section>

                <!-- VACUNA -->
                <section class="atencion__vista" data-vista="vacuna">
                    <div class="procedimiento">
                        <form id="formVacunaAtencion" class="procedimiento__form" novalidate>
                            <input type="hidden" name="id_mascota" value="<?= (int) $mascota['id_mascota'] ?>">
                            <fieldset class="atencion__fieldset" data-requiere-inicio <?= $enCurso ? '' : 'disabled' ?>>
                                <div class="campo-fila">
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Vacuna *</span>
                                        <input type="text" name="nombre_vacuna" list="listaVacunas" required maxlength="150" data-etiqueta="el nombre de la vacuna" placeholder="Escribe o elige del catálogo">
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Laboratorio</span>
                                        <input type="text" name="laboratorio" list="listaLaboratorios" maxlength="150">
                                    </label>
                                </div>
                                <div class="campo-fila">
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Lote</span>
                                        <input type="text" name="lote" maxlength="100">
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Aplicada el *</span>
                                        <input type="date" name="fecha_aplicacion" required max="<?= $hoyIso ?>" value="<?= $hoyIso ?>" data-etiqueta="la fecha de aplicación">
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Próxima dosis</span>
                                        <input type="date" name="fecha_proxima" min="<?= $hoyIso ?>">
                                    </label>
                                </div>
                                <label class="campo">
                                    <span class="campo__etiqueta">Observaciones</span>
                                    <input type="text" name="observaciones" maxlength="5000">
                                </label>
                                <button type="submit" class="atencion__btn atencion__btn--primario"><i class="fas fa-syringe"></i> Registrar vacuna</button>
                            </fieldset>
                        </form>

                        <div class="procedimiento__registros">
                            <span class="paciente__etiqueta">Vacunas aplicadas</span>
                            <ul class="registros" id="listaVacunasAplicadas">
                                <?php if (empty($vacunas)): ?>
                                    <li class="atencion__vacio">Aún no tiene vacunas registradas.</li>
                                <?php endif; ?>
                                <?php foreach ($vacunas as $v): ?>
                                    <li>
                                        <?php $registroHistoria = $v; ?>
                                        <?php include __DIR__ . '/../partials/historial_procedimiento.php'; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- DESPARASITACIÓN -->
                <section class="atencion__vista" data-vista="desparasitacion">
                    <div class="procedimiento">
                        <form id="formDesparasitacionAtencion" class="procedimiento__form" novalidate>
                            <input type="hidden" name="id_mascota" value="<?= (int) $mascota['id_mascota'] ?>">
                            <fieldset class="atencion__fieldset" data-requiere-inicio <?= $enCurso ? '' : 'disabled' ?>>
                                <div class="campo-fila">
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Tipo *</span>
                                        <select name="tipo" required data-etiqueta="el tipo">
                                            <option value="interna">Interna (pastillas / jarabe)</option>
                                            <option value="externa">Externa (pipeta / collar)</option>
                                        </select>
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Producto *</span>
                                        <input type="text" name="producto" list="listaProductos" required maxlength="150" data-etiqueta="el producto" placeholder="Escribe o elige del catálogo">
                                    </label>
                                </div>
                                <div class="campo-fila">
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Periodicidad *</span>
                                        <select name="periodicidad" required data-etiqueta="la periodicidad">
                                            <option value="mensual">Mensual</option>
                                            <option value="trimestral" selected>Trimestral</option>
                                            <option value="semestral">Semestral</option>
                                        </select>
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Aplicada el *</span>
                                        <input type="date" name="fecha_aplicacion" required max="<?= $hoyIso ?>" value="<?= $hoyIso ?>" data-etiqueta="la fecha de aplicación">
                                    </label>
                                </div>
                                <label class="campo">
                                    <span class="campo__etiqueta">Observaciones</span>
                                    <input type="text" name="observaciones" maxlength="5000">
                                </label>
                                <p class="procedimiento__nota"><i class="fas fa-info-circle"></i> La próxima aplicación se calcula sola según la periodicidad.</p>
                                <button type="submit" class="atencion__btn atencion__btn--primario"><i class="fas fa-shield-alt"></i> Registrar desparasitación</button>
                            </fieldset>
                        </form>

                        <div class="procedimiento__registros">
                            <span class="paciente__etiqueta">Desparasitaciones aplicadas</span>
                            <ul class="registros" id="listaDesparasitacionesAplicadas">
                                <?php if (empty($desparasitaciones)): ?>
                                    <li class="atencion__vacio">Aún no tiene desparasitaciones registradas.</li>
                                <?php endif; ?>
                                <?php foreach ($desparasitaciones as $d): ?>
                                    <li>
                                        <?php $registroHistoria = $d; ?>
                                        <?php include __DIR__ . '/../partials/historial_procedimiento.php'; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- HISTORIAL -->
                <section class="atencion__vista" data-vista="historial">
                    <ul class="historial" id="atencionHistorial">
                        <?php if (empty($consultas)): ?>
                            <li class="atencion__vacio">Es su primera consulta: todavía no hay historial.</li>
                        <?php endif; ?>
                        <?php foreach ($consultas as $con): ?>
                            <li>
                                <details class="historia-fila">
                                    <summary class="historia-fila__resumen">
                                        <time class="historia-fila__fecha"><?= $fechaCorta($con['fecha_hora']) ?></time>
                                        <span class="historia-fila__clinica"><?= $e($con['clinica_nombre'] ?? 'Clínica actual') ?></span>
                                        <strong class="historia-fila__motivo"><?= $e($con['motivo_consulta']) ?></strong>
                                        <span class="historia-fila__diagnostico"><?= $e($con['diagnostico']) ?></span>
                                        <span class="historia-fila__chips">
                                            <?php if (empty($con['id_cita'])): ?><span class="consultation-sin-cita-badge">Sin cita</span><?php endif; ?>
                                            <span class="historia-fila__chip"><?= $textoPeso($con['peso'] ?? null) ?></span>
                                        </span>
                                        <i class="fas fa-chevron-down historia-fila__flecha" aria-hidden="true"></i>
                                    </summary>
                                    <div class="historia-fila__detalle">
                                        <p><strong>Motivo:</strong> <?= $e($con['motivo_consulta']) ?></p>
                                        <p><strong>Diagnóstico:</strong> <?= $e($con['diagnostico']) ?></p>
                                        <p><strong>Veterinario:</strong> <?= $e($con['veterinario'] ?? '') ?></p>
                                        <?php if (!empty($con['sintomas'])): ?><p><strong>Síntomas:</strong> <?= $e(implode(', ', array_column($con['sintomas'], 'etiqueta'))) ?></p><?php endif; ?>
                                        <p><strong>Plan:</strong> <?= $e($con['plan_tratamiento'] ?? '') ?></p>
                                        <p>Temperatura <?= $e($con['temperatura'] ?? '—') ?> °C · F.C. <?= $e($con['frecuencia_cardiaca'] ?? '—') ?> lpm</p>
                                    </div>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <!-- ── Acciones ───────────────────────────────────────────── -->
            <footer class="atencion__acciones" id="accionesAtencion" <?= $enCurso ? '' : 'hidden' ?>>
                <span class="atencion__borrador" id="estadoBorrador"></span>
                <div class="atencion__acciones-botones">
                    <?php if (!$consultaRegistrada): ?>
                        <div class="atencion__nav-pasos" id="navPasos">
                            <button type="button" class="atencion__btn atencion__btn--suave" id="btnPasoAnterior"><i class="fas fa-chevron-left"></i> Anterior</button>
                            <button type="button" class="atencion__btn atencion__btn--suave" id="btnPasoSiguiente">Siguiente <i class="fas fa-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                    <?php // M2 C8 (H5): el botón principal va en el azul de marca. ?>
                    <button type="button" class="atencion__btn atencion__btn--primario" id="btnFinalizarAtencion">
                        <i class="fas fa-check-circle"></i> Finalizar atención
                    </button>
                </div>
            </footer>
        </main>
    </div>

    <datalist id="listaVacunas"></datalist>
    <datalist id="listaLaboratorios"></datalist>
    <datalist id="listaProductos"></datalist>
</section>
<?php include __DIR__ . '/../partials/modal_alerta_medica.php'; ?>
