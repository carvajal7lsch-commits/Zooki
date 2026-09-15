<?php
/**
 * Panel de inicio del veterinario: «Mi día» (HU-18, HU-20).
 * Los datos llegan de PanelController::datosVeterinario() en $panel.
 */
require_once __DIR__ . '/../../helpers/ResumenPanel.php';

if (!isset($panel)) {
    require_once __DIR__ . '/../../controllers/PanelController.php';
    $panel = (new PanelController())->datosVeterinario($_SESSION['usuario_doc']);
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nombre = ResumenPanel::primerNombre($_SESSION['usuario_nombre'] ?? '');
$fichaUrl = fn($c) => 'index.php?action=vet_pacientes&propietario=' . rawurlencode($c['doc_propietario']) . '&mascota=' . (int) $c['id_mascota'];
$atencionUrl = fn($c) => 'index.php?action=vet_atencion&id_cita=' . (int) $c['id_cita'];
$cont = $panel['contadores'];
$sig = $panel['siguiente'];
$plural = fn(int $n, string $uno, string $varios) => $n === 1 ? $uno : $varios;

/** Botón de la acción disponible para una cita; vacío si no hay ninguna. */
$botonAccion = function (array $c, bool $compacto = false) use ($e, $atencionUrl) {
    $accion = $c['accion'] ?? ['tipo' => null];
    $tam = $compacto ? ' panel-btn--sm' : '';
    switch ($accion['tipo']) {
        case 'continuar':
            $texto = $c['estado'] === 'sin_cerrar' ? 'Resolver' : 'Continuar atención';
            return '<a class="panel-btn panel-btn--pri' . $tam . '" href="' . $e($atencionUrl($c)) . '"><i class="fas fa-arrow-right"></i> ' . $texto . '</a>';
        case 'iniciar':
            return '<button type="button" class="panel-btn panel-btn--pri' . $tam . '" data-iniciar="' . (int) $c['id_cita'] . '"><i class="fas fa-play"></i> Iniciar atención</button>';
        case 'esperar':
            return '<button type="button" class="panel-btn panel-btn--pri' . $tam . '" data-iniciar="' . (int) $c['id_cita'] . '" data-desde="' . $e($accion['desde_iso']) . '" disabled title="Se habilita 15 minutos antes de la cita">'
                . '<i class="far fa-clock"></i> Desde las ' . $e(ResumenPanel::hora($accion['desde'])) . '</button>';
        default:
            return '';
    }
};
?>
<div class="panel panel--vet">

    <section class="panel-hero">
        <div class="panel-hero__contenido">
            <p class="panel-hero__fecha"><?= $e($panel['fecha']) ?></p>
            <h2 class="panel-hero__titulo"><?= $e($panel['saludo']) ?>, <?= $e($nombre) ?></h2>
            <ul class="panel-hero__resumen">
                <li><strong><?= (int) $cont['citas'] ?></strong> <?= $plural((int) $cont['citas'], 'cita hoy', 'citas hoy') ?></li>
                <li><strong><?= (int) $cont['atendidas'] ?></strong> <?= $plural((int) $cont['atendidas'], 'atendida', 'atendidas') ?></li>
                <li><strong><?= (int) $cont['por_atender'] ?></strong> por atender</li>
            </ul>
            <div class="panel-hero__acciones">
                <a class="panel-btn panel-btn--claro" href="index.php?action=vet_consultas"><i class="fas fa-plus"></i> Consulta sin cita</a>
                <a class="panel-btn panel-btn--vidrio" href="index.php?action=vet_agenda"><i class="far fa-calendar"></i> Calendario</a>
            </div>
        </div>
        <img class="panel-hero__imagen" src="img/pets.png" alt="">
    </section>

    <div class="panel-cuerpo">
        <div class="panel-col">

            <!-- Siguiente paciente -->
            <section class="panel-card panel-siguiente" aria-labelledby="tituloSiguiente">
                <h3 class="panel-card__titulo" id="tituloSiguiente">
                    <?= $sig && $sig['estado'] === 'en_curso' ? 'En atención' : 'Siguiente paciente' ?>
                </h3>
                <?php if ($sig): ?>
                    <div class="panel-siguiente__cuerpo">
                        <div class="panel-siguiente__hora"><?= $e(ResumenPanel::hora($sig['hora'])) ?></div>
                        <div class="panel-siguiente__info">
                            <p class="panel-siguiente__mascota"><?= $e($sig['mascota']) ?>
                                <span class="panel-badge" data-estado="<?= $e($sig['estado']) ?>"><?= $e(ResumenPanel::etiquetaEstado($sig['estado'])) ?></span>
                            </p>
                            <p class="panel-texto-sec"><?= $e($sig['especie']) ?> · <?= $e($sig['tipo']) ?> · <?= $e($sig['propietario']) ?></p>
                            <?php if (!empty($sig['motivo'])): ?>
                                <p class="panel-texto-sec">Motivo: <?= $e($sig['motivo']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="panel-siguiente__acciones">
                            <?= $botonAccion($sig) ?>
                            <a class="panel-btn panel-btn--sec" href="<?= $e($fichaUrl($sig)) ?>"><i class="far fa-folder-open"></i> Ver ficha</a>
                        </div>
                    </div>
                <?php elseif ($cont['citas'] > 0): ?>
                    <p class="panel-vacio panel-vacio--ok"><i class="fas fa-check-circle"></i> No te quedan pacientes por atender hoy.</p>
                <?php else: ?>
                    <p class="panel-vacio"><i class="far fa-calendar"></i> No tienes citas agendadas para hoy.</p>
                <?php endif; ?>
            </section>

            <!-- Agenda de hoy -->
            <section class="panel-card panel-card--llena" aria-labelledby="tituloAgenda">
                <div class="panel-card__cabecera">
                    <h3 class="panel-card__titulo" id="tituloAgenda">Mi agenda de hoy</h3>
                    <a class="panel-enlace" href="index.php?action=vet_agenda">Ver calendario <i class="fas fa-chevron-right"></i></a>
                </div>
                <?php if (!$panel['agenda']): ?>
                    <p class="panel-vacio">Sin citas hoy.</p>
                <?php else: ?>
                    <ul class="panel-lista panel-desplazable">
                        <?php foreach ($panel['agenda'] as $c): ?>
                            <li class="panel-fila" data-estado="<?= $e($c['estado']) ?>">
                                <span class="panel-fila__hora"><?= $e(ResumenPanel::hora($c['hora'])) ?></span>
                                <div class="panel-fila__info">
                                    <a class="panel-fila__titulo" href="<?= $e($fichaUrl($c)) ?>"><?= $e($c['mascota']) ?></a>
                                    <span class="panel-texto-sec"><?= $e($c['tipo']) ?> · <?= $e($c['propietario']) ?></span>
                                </div>
                                <span class="panel-badge" data-estado="<?= $e($c['estado']) ?>"><?= $e(ResumenPanel::etiquetaEstado($c['estado'])) ?></span>
                                <div class="panel-fila__accion"><?= $botonAccion($c, true) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <aside class="panel-col">

            <!-- Atenciones que exigen cierre (RN-410 / RN-411) -->
            <section class="panel-card panel-card--acotada" aria-labelledby="tituloPendientes">
                <h3 class="panel-card__titulo" id="tituloPendientes">Requiere tu atención</h3>
                <?php if (!$panel['pendientes']): ?>
                    <p class="panel-vacio panel-vacio--ok"><i class="fas fa-check-circle"></i> Todo al día.</p>
                <?php else: ?>
                    <ul class="panel-lista panel-desplazable">
                        <?php foreach ($panel['pendientes'] as $c): ?>
                            <li class="panel-fila panel-fila--alerta" data-estado="<?= $e($c['estado']) ?>">
                                <div class="panel-fila__info">
                                    <span class="panel-fila__titulo"><?= $e($c['mascota']) ?></span>
                                    <span class="panel-texto-sec"><?= $e(ResumenPanel::etiquetaEstado($c['estado'])) ?> · <?= $e($c['dia']) ?>, <?= $e(ResumenPanel::hora($c['hora'])) ?></span>
                                </div>
                                <div class="panel-fila__accion"><?= $botonAccion($c, true) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="panel-nota">Regístrales la consulta o ciérralas sin consulta indicando el motivo.</p>
                <?php endif; ?>
            </section>

            <!-- Próximas dosis de sus pacientes (HU-20) -->
            <section class="panel-card panel-card--llena" aria-labelledby="tituloRecordatorios">
                <div class="panel-card__cabecera">
                    <h3 class="panel-card__titulo" id="tituloRecordatorios">Vacunas y desparasitaciones</h3>
                    <span class="panel-texto-sec">Próximos 7 días</span>
                </div>
                <?php if (!$panel['recordatorios']): ?>
                    <p class="panel-vacio">Ninguno de tus pacientes tiene dosis próximas.</p>
                <?php else: ?>
                    <div class="panel-desplazable">
                        <?php foreach ($panel['recordatorios'] as $grupo): ?>
                            <div class="panel-grupo">
                                <p class="panel-grupo__dia"><?= $e($grupo['etiqueta']) ?> <span class="panel-grupo__conteo"><?= count($grupo['items']) ?></span></p>
                                <ul class="panel-lista">
                                    <?php foreach ($grupo['items'] as $r): ?>
                                        <li class="panel-fila" data-tipo="<?= $r['tipo'] === 'vacuna' ? 'vacunacion' : 'desparasitacion' ?>">
                                            <span class="panel-fila__icono"><i class="fas <?= $r['tipo'] === 'vacuna' ? 'fa-syringe' : 'fa-shield-alt' ?>"></i></span>
                                            <div class="panel-fila__info">
                                                <a class="panel-fila__titulo" href="<?= $e($fichaUrl($r)) ?>"><?= $e($r['mascota']) ?></a>
                                                <span class="panel-texto-sec"><?= $r['tipo'] === 'vacuna' ? 'Vacuna' : 'Desparasitación' ?>: <?= $e($r['nombre']) ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
