<?php
/**
 * «Tu día» del Inicio del veterinario (HU-6.1, RE-6.1.9). Lo pintan el primer
 * render (area.php) y PanelController::diaAjax al elegir otro día.
 * Recibe $dia de PanelController::dia().
 */
require_once __DIR__ . '/../../../helpers/ResumenPanel.php';

$hDia = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$urlAgendar = static fn (string $fecha, ?string $hora = null): string => 'index.php?action=vet_agenda&agendar=' . rawurlencode($fecha)
    . ($hora !== null ? '&hora=' . rawurlencode($hora) : '');
$urlCita = static function (array $c): string {
    if (in_array($c['estado'], ['en_curso', 'pausada', 'sin_cerrar'], true)) {
        return 'index.php?action=vet_atencion&id_cita=' . (int) $c['id_cita'];
    }
    return 'index.php?action=vet_pacientes&propietario=' . (int) $c['id_propietario'] . '&mascota=' . (int) $c['id_mascota'];
};
$resumenDia = $dia['citas'] === 0
    ? 'Sin citas'
    : $dia['citas'] . ($dia['citas'] === 1 ? ' cita' : ' citas') . ' · toca un día del calendario para ver otro';
?>
<div class="inicio-cabecera">
    <h2 class="inicio-titulo inicio-titulo--dia" id="inicioTituloDia">Tu día · <?= $hDia($dia['titulo']) ?></h2>
    <span class="inicio-meta"><?= $hDia($resumenDia) ?></span>
</div>
<?php if ($dia['aviso'] !== null): ?>
    <?php
    $atencionesDia = array_values(array_filter($dia['lineas'], static fn($linea) => $linea['tipo'] === 'cita' && in_array($linea['cita']['estado'], ['en_curso', 'pausada', 'sin_cerrar'], true)));
    $atencionDia = $atencionesDia[0]['cita'] ?? null;
    ?>
    <div class="inicio-aviso" role="status">
        <?php if ($atencionDia !== null): ?>
            <span>La atención de <strong><?= $hDia($atencionDia['mascota']) ?></strong> sigue abierta. Registra la consulta al terminar para actualizar los horarios libres.</span>
            <a href="<?= $hDia($urlCita($atencionDia)) ?>">Continuar atención <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <?php else: ?>
            <span>Los horarios libres están pendientes de actualización.</span>
            <a href="index.php?action=vet_agenda">Revisar agenda <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($dia['citas'] > 0): ?>
    <ol class="inicio-lineas<?= count($dia['lineas']) <= 3 ? ' inicio-lineas--breve' : '' ?>" aria-label="Agenda del día">
        <?php foreach ($dia['lineas'] as $linea): ?>
            <li class="inicio-linea inicio-linea--<?= $hDia($linea['tipo']) ?><?= $linea['tipo'] === 'cita' ? ' inicio-linea--' . $hDia($linea['estado']) : '' ?>">
                <span class="inicio-linea__hora"><?= $hDia($linea['hora']) ?></span>
                <span class="inicio-linea__eje" aria-hidden="true"><span class="inicio-linea__nodo"></span></span>
                <?php if ($linea['tipo'] === 'cita'): ?>
                    <a class="inicio-cita" href="<?= $hDia($urlCita($linea['cita'])) ?>">
                        <span class="inicio-cita__texto"><strong><?= $hDia($linea['mascota']) ?></strong><?php if ($linea['texto'] !== ''): ?> <span class="inicio-meta">· <?= $hDia($linea['texto']) ?></span><?php endif; ?></span>
                        <span class="inicio-cita__estado"><?= $hDia($linea['etiqueta']) ?></span>
                    </a>
                <?php elseif ($linea['tipo'] === 'libre'): ?>
                    <a class="inicio-hueco" href="<?= $hDia($urlAgendar($linea['fecha'], $linea['hora_iso'])) ?>" aria-label="<?= $hDia($linea['texto'] . ' desde las ' . $linea['hora'] . ': agendar cita') ?>">
                        <span><?= $hDia($linea['texto']) ?></span>
                        <span class="inicio-hueco__accion" aria-hidden="true">+ Agendar</span>
                    </a>
                <?php else: ?>
                    <span class="inicio-bloqueo"><?= $hDia($linea['texto']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php else: ?>
    <?php
    if ($dia['pasado']) {
        $textoLibre = 'No tuviste citas este día.';
    } elseif ($dia['sin_jornada']) {
        $textoLibre = 'No tienes citas ni horario de atención este día.';
    } elseif ($dia['minutos_libres'] > 0) {
        $textoLibre = 'No tienes citas este día. Te quedan ' . ResumenPanel::duracion($dia['minutos_libres']) . ' libres para agendar.';
    } else {
        $textoLibre = 'No tienes citas este día y ya no quedan horas libres para agendar.';
    }
    ?>
    <div class="inicio-libre">
        <span class="inicio-libre__cuarto" aria-hidden="true"></span>
        <img class="inicio-libre__mascota" src="img/golden-retriever.png" alt="" loading="lazy" decoding="async">
        <div class="inicio-libre__texto">
            <p class="inicio-libre__titulo"><?= $dia['pasado'] ? 'Sin citas' : 'Día libre' ?></p>
            <p class="inicio-meta inicio-meta--grande"><?= $hDia($textoLibre) ?></p>
            <p class="inicio-libre__proximo"><span class="inicio-meta">Próximo día con citas:</span>
                <strong><?= $dia['proximo'] === null ? 'sin citas próximas' : $hDia($dia['proximo']['texto']) ?></strong></p>
            <?php if (!$dia['pasado'] && $dia['minutos_libres'] > 0): ?>
                <a class="inicio-boton inicio-boton--primario" href="<?= $hDia($urlAgendar($dia['fecha'])) ?>">Agendar cita</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
