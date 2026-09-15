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
    'sin_cerrar' => 'Sin cerrar', 'cerrada_sin_consulta' => 'Cerrada sin consulta',
];
$estadoActual = strtolower($cita['estado'] ?? 'pendiente');
$consultaRegistrada = !empty($consultaCita);
// RN-410: una atención que quedó sin cerrar se sigue documentando igual.
$enCurso = in_array($estadoActual, ['en_curso', 'sin_cerrar'], true);
$abierta = in_array($estadoActual, ['pendiente', 'confirmada'], true);
// RN-408: se inicia el día de la cita desde 15 minutos antes de su hora
// ($esDiaDeLaCita viene del controlador con esa regla ya aplicada).
$porIniciar = $abierta && $esDiaDeLaCita;
$pesoReferencia = $resumenClinico['ultimo_peso'] ?? ($mascota['peso'] ?? null);
$hoyIso = date('Y-m-d');
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
        <aside class="atencion__paciente">
            <div class="paciente__cabecera">
                <img src="<?= $e($fotoMascota) ?>" alt="" class="paciente__foto" id="atencionFoto">
                <div>
                    <h2><?= $e($mascota['nombre'] ?? 'Sin nombre') ?></h2>
                    <p><?= $e($mascota['nombre_especie'] ?? '') ?><?= !empty($mascota['nombre_raza']) ? ' · ' . $e($mascota['nombre_raza']) : '' ?></p>
                </div>
            </div>

            <dl class="paciente__datos">
                <div><dt>Edad</dt><dd><?= $e($edadMascota) ?></dd></div>
                <div><dt>Sexo</dt><dd><?= $e($mascota['sexo'] ?? '—') ?></dd></div>
                <div><dt>Peso</dt><dd><?= $pesoReferencia !== null && $pesoReferencia !== '' ? $e($pesoReferencia) . ' kg' : '—' ?></dd></div>
                <div><dt>Historia</dt><dd><?= $e($mascota['numero_historia_clinica'] ?: 'Nueva') ?></dd></div>
            </dl>

            <div class="paciente__bloque">
                <span class="paciente__etiqueta">Propietario</span>
                <p class="paciente__texto"><?= $e($propietario['nombre_completo'] ?? '—') ?></p>
                <?php if (!empty($propietario['telefono'])): ?>
                    <a class="paciente__telefono" href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $propietario['telefono'])) ?>">
                        <i class="fas fa-phone"></i> <?= $e($propietario['telefono']) ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="paciente__bloque">
                <span class="paciente__etiqueta">Motivo de la cita</span>
                <p class="paciente__texto"><?= $e($cita['motivo'] ?: 'Sin motivo indicado') ?></p>
            </div>

            <div class="paciente__bloque">
                <span class="paciente__etiqueta">Pendientes</span>
                <?php if (empty($resumenClinico['alertas'])): ?>
                    <p class="paciente__ok"><i class="fas fa-check-circle"></i> Vacunas y desparasitación al día</p>
                <?php else: ?>
                    <ul class="paciente__alertas">
                        <?php foreach ($resumenClinico['alertas'] as $alerta): ?>
                            <li class="alerta alerta--<?= $e($alerta['estado']) ?>">
                                <i class="fas <?= $alerta['clase'] === 'vacuna' ? 'fa-syringe' : 'fa-shield-alt' ?>"></i>
                                <span><?= $e($alerta['clase'] === 'vacuna' ? $alerta['nombre'] : 'Desparasitación ' . $alerta['nombre']) ?></span>
                                <small><?= $alerta['estado'] === 'vencida' ? 'Vencida ' : 'Vence ' ?><?= $fechaCorta($alerta['fecha']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if (!empty($resumenClinico['ultima_consulta'])): ?>
                <div class="paciente__bloque">
                    <span class="paciente__etiqueta">Última consulta · <?= $fechaCorta($resumenClinico['ultima_consulta']['fecha']) ?></span>
                    <p class="paciente__texto paciente__texto--recortado"><?= $e($resumenClinico['ultima_consulta']['diagnostico']) ?></p>
                </div>
            <?php endif; ?>
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
                                <div><span>Peso</span><strong><?= $e($consultaCita['peso'] ?? '—') ?> kg</strong></div>
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
                        <form id="formConsultaAtencion" class="consulta-form" enctype="multipart/form-data" novalidate>
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
                                    <label class="campo">
                                        <span class="campo__etiqueta">Motivo de la consulta *</span>
                                        <input type="text" name="motivo" required maxlength="5000" data-etiqueta="el motivo" value="<?= $e($cita['motivo'] ?? '') ?>">
                                    </label>
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Anamnesis</span>
                                        <textarea name="anamnesis" maxlength="5000" placeholder="Historia relatada por el propietario, síntomas, desde cuándo…"></textarea>
                                    </label>
                                </div>

                                <div class="atencion__paso">
                                    <div class="signos">
                                        <label class="signo">
                                            <i class="fas fa-weight signo__icono signo__icono--peso"></i>
                                            <span class="campo__etiqueta">Peso</span>
                                            <span class="signo__entrada"><input type="number" name="peso" step="0.01" min="0.01" max="200" data-etiqueta="el peso" placeholder="<?= $pesoReferencia !== null ? $e($pesoReferencia) : '0.00' ?>"><em>kg</em></span>
                                            <small>Entre 0.01 y 200 kg<?= $pesoReferencia !== null ? ' · último: ' . $e($pesoReferencia) . ' kg' : '' ?></small>
                                        </label>
                                        <label class="signo">
                                            <i class="fas fa-thermometer-half signo__icono signo__icono--temp"></i>
                                            <span class="campo__etiqueta">Temperatura</span>
                                            <span class="signo__entrada"><input type="number" name="temperatura" step="0.1" min="25" max="45" data-etiqueta="la temperatura" placeholder="38.5"><em>°C</em></span>
                                            <small>Entre 25 y 45 °C</small>
                                        </label>
                                        <label class="signo">
                                            <i class="fas fa-heartbeat signo__icono signo__icono--fc"></i>
                                            <span class="campo__etiqueta">Frecuencia cardíaca</span>
                                            <span class="signo__entrada"><input type="number" name="frecuencia_cardiaca" step="1" min="10" max="400" data-etiqueta="la frecuencia cardíaca" placeholder="100"><em>lpm</em></span>
                                            <small>Entre 10 y 400 lpm</small>
                                        </label>
                                        <label class="signo">
                                            <i class="fas fa-lungs signo__icono signo__icono--fr"></i>
                                            <span class="campo__etiqueta">Frecuencia respiratoria</span>
                                            <span class="signo__entrada"><input type="number" name="frecuencia_respiratoria" step="1" min="5" max="150" data-etiqueta="la frecuencia respiratoria" placeholder="24"><em>rpm</em></span>
                                            <small>Entre 5 y 150 rpm</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="atencion__paso">
                                    <label class="campo campo--crece">
                                        <span class="campo__etiqueta">Diagnóstico *</span>
                                        <textarea name="diagnostico" required maxlength="5000" data-etiqueta="el diagnóstico"></textarea>
                                    </label>
                                    <div class="campo-fila">
                                        <label class="campo campo--crece">
                                            <span class="campo__etiqueta">Plan / recomendaciones</span>
                                            <textarea name="plan_tratamiento" maxlength="5000"></textarea>
                                        </label>
                                        <label class="campo campo--crece">
                                            <span class="campo__etiqueta">Observaciones</span>
                                            <textarea name="observaciones" maxlength="5000"></textarea>
                                        </label>
                                    </div>
                                </div>

                                <div class="atencion__paso">
                                    <div class="tratamientos__cabecera">
                                        <p>Medicamentos prescritos en esta consulta.</p>
                                        <button type="button" class="atencion__btn atencion__btn--suave" id="btnAgregarTratamiento">
                                            <i class="fas fa-plus"></i> Agregar medicamento
                                        </button>
                                    </div>
                                    <div class="tratamientos__lista">
                                        <p class="atencion__vacio" id="tratamientosVacio">Sin medicamentos. Agrega uno si hace falta.</p>
                                        <div id="treatmentsList"></div>
                                    </div>
                                </div>

                                <div class="atencion__paso">
                                    <label class="adjuntos file-upload-zone">
                                        <input type="file" name="archivos[]" id="archivosConsulta" multiple accept=".jpg,.jpeg,.png,.pdf">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Arrastra o elige radiografías, exámenes o fotos</span>
                                        <small>JPG, PNG o PDF · máximo 10 MB cada uno</small>
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
                                    <li class="registro">
                                        <div><strong><?= $e($v['nombre_vacuna']) ?></strong><span><?= $e($v['laboratorio'] ?: 'Sin laboratorio') ?></span></div>
                                        <div class="registro__fechas"><span>Aplicada <?= $fechaCorta($v['fecha_aplicacion']) ?></span><?php if (!empty($v['fecha_proxima_dosis'])): ?><span>Próxima <?= $fechaCorta($v['fecha_proxima_dosis']) ?></span><?php endif; ?></div>
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
                                    <li class="registro">
                                        <div><strong><?= $e($d['producto']) ?></strong><span><?= $e(ucfirst($d['tipo'])) ?> · <?= $e($d['periodicidad'] ?? '') ?></span></div>
                                        <div class="registro__fechas"><span>Aplicada <?= $fechaCorta($d['fecha_aplicacion']) ?></span><?php if (!empty($d['fecha_proxima'])): ?><span>Próxima <?= $fechaCorta($d['fecha_proxima']) ?></span><?php endif; ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- HISTORIAL -->
                <section class="atencion__vista" data-vista="historial">
                    <ul class="historial">
                        <?php if (empty($consultas)): ?>
                            <li class="atencion__vacio">Es su primera consulta: todavía no hay historial.</li>
                        <?php endif; ?>
                        <?php foreach ($consultas as $con): ?>
                            <li class="historial__item">
                                <div class="historial__cabecera">
                                    <span class="historial__fecha"><?= $fechaCorta($con['fecha_hora']) ?></span>
                                    <strong><?= $e($con['motivo_consulta']) ?></strong>
                                    <span class="historial__vet"><i class="fas fa-user-md"></i> <?= $e($con['veterinario'] ?? '') ?></span>
                                </div>
                                <p><span>Diagnóstico:</span> <?= $e($con['diagnostico']) ?></p>
                                <?php if (!empty($con['plan_tratamiento'])): ?><p><span>Plan:</span> <?= $e($con['plan_tratamiento']) ?></p><?php endif; ?>
                                <?php if (!empty($con['peso']) || !empty($con['temperatura'])): ?>
                                    <div class="historial__signos">
                                        <?php if (!empty($con['peso'])): ?><span><i class="fas fa-weight"></i> <?= $e($con['peso']) ?> kg</span><?php endif; ?>
                                        <?php if (!empty($con['temperatura'])): ?><span><i class="fas fa-thermometer-half"></i> <?= $e($con['temperatura']) ?> °C</span><?php endif; ?>
                                        <?php if (!empty($con['frecuencia_cardiaca'])): ?><span><i class="fas fa-heartbeat"></i> <?= $e($con['frecuencia_cardiaca']) ?> lpm</span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
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
                    <button type="button" class="atencion__btn atencion__btn--exito" id="btnFinalizarAtencion">
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
