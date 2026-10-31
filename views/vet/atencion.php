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
         data-consulta-registrada="<?= $consultaRegistrada ? '1' : '0' ?>"
         data-id-veterinario="<?= (int) ($cita['id_veterinario'] ?? Contexto::idUsuario()) ?>"
         data-id-clinica="<?= (int) Contexto::clinicaActiva() ?>">

    <!-- ── Barra superior ─────────────────────────────────────────── -->
    <header class="atencion__barra">
        <a href="index.php?action=vet_agenda" class="atencion__volver" title="Volver a la agenda">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="atencion__titulo">
            <h1>Atención de <?= $e($mascota['nombre'] ?? 'la mascota') ?> <span class="atencion__estado estado--<?= $e($estadoActual) ?>" id="atencionEstado"><?= $e($estadoLabel[$estadoActual] ?? $estadoActual) ?></span></h1>
            <p>
                <?= $cita['fecha'] === $hoyIso ? 'Hoy' : $fechaCorta($cita['fecha']) ?> <?= $e(substr($cita['hora'], 0, 5)) ?>
                <?php if ($tipoCitaNombre !== ''): ?> · <?= $e($tipoCitaNombre) ?><?php endif; ?> · Cita #<?= (int) $cita['id_cita'] ?>
            </p>
        </div>
        <?php require __DIR__ . '/../partials/cabecera_cuenta.php'; ?>
    </header>

    <div class="atencion__cuerpo">

        <!-- ── Ficha del paciente (fija) ──────────────────────────── -->
        <?php // M2 C8 (H4): más compacta; los datos en una cuadrícula densa y el resto en filas cortas. ?>
        <section class="atencion-franja" aria-label="Paciente">
            <div class="atencion-franja__anillo"><?php if ($tieneFoto): ?><img src="<?= $e($fotoMascota) ?>" alt="" id="atencionFoto"><?php else: ?><span><?= $e(PresentacionPlataforma::iniciales((string) ($mascota['nombre'] ?? '?'))) ?></span><?php endif; ?></div>
            <div class="atencion-franja__identidad"><strong><?= $e($mascota['nombre'] ?? 'Sin nombre') ?></strong><small><?= $e(implode(' · ', array_filter([$mascota['nombre_especie'] ?? '', $mascota['nombre_raza'] ?? '', $mascota['sexo'] ?? '', $edadMascota]))) ?></small></div>
            <div class="atencion-franja__datos"><span><?= $textoPeso($pesoReferencia) ?></span><span><?= $e($mascota['numero_historia_clinica'] ?: 'Historia nueva') ?></span><?php if (!empty($propietario['telefono'])): ?><a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $propietario['telefono'])) ?>"><?= $e($propietario['telefono']) ?></a><?php endif; ?></div>
            <section class="atencion-franja__alertas" data-alertas-medicas data-cargar data-alertas-chips data-id-mascota="<?= (int) $mascota['id_mascota'] ?>" data-nombre-mascota="<?= $e($mascota['nombre'] ?? '') ?>" aria-label="Alergias y alertas"></section>
            <div class="atencion-franja__pasos" data-pasos-paciente></div>
        </section>

        <!-- ── Panel de atención ──────────────────────────────────── -->
        <main class="atencion__panel">
            <details class="atencion-procedimientos"><summary>Consulta, historial y procedimientos</summary><nav class="atencion__tabs" role="tablist">
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
            </nav></details>

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
                        <form id="formConsultaAtencion" class="consulta-form consulta-formulario" autocomplete="off" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="id_mascota" value="<?= (int) $mascota['id_mascota'] ?>">
                            <input type="hidden" name="id_cita" value="<?= (int) $cita['id_cita'] ?>">

                            <fieldset class="atencion__fieldset" data-requiere-inicio <?= $enCurso ? '' : 'disabled' ?>>
                                <?php $consultaEnAtencion = true; ?>
                                <?php include __DIR__ . '/../partials/consulta_pasos.php'; ?>
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
                                    <?php // C8.2: el mismo resumen en dos grupos que filaHistorialCompacto() de medical-module.js. ?>
                                    <summary class="historia-fila__resumen">
                                        <span class="historia-fila__lineas">
                                            <span class="historia-fila__linea historia-fila__linea--principal">
                                                <time class="historia-fila__fecha"><?= $fechaCorta($con['fecha_hora']) ?></time>
                                                <strong class="historia-fila__titulo"><?= $e(($con['diagnostico'] ?? '') !== '' ? $con['diagnostico'] : $con['motivo_consulta']) ?></strong>
                                            </span>
                                            <span class="historia-fila__linea historia-fila__linea--contexto">
                                                <span class="historia-fila__clinica"><?= $e($con['clinica_nombre'] ?? 'Clínica actual') ?></span>
                                                <span class="historia-fila__chips">
                                                    <?php if (empty($con['id_cita'])): ?><span class="consultation-sin-cita-badge">Sin cita</span><?php endif; ?>
                                                    <span class="historia-fila__chip"><?= $textoPeso($con['peso'] ?? null) ?></span>
                                                </span>
                                            </span>
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
