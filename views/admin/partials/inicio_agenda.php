<?php
/**
 * Agenda del día por veterinario del Inicio del administrador (HU-6.2,
 * RE-6.2.9): una columna por veterinario activo, incluso sin citas, con sus
 * citas y su estado, los huecos libres reales (RN-415), el almuerzo, los
 * cierres, las ausencias y su ocupación. Con más de tres veterinarios, la
 * agenda se desplaza en horizontal dentro de la tarjeta (decisión del dueño).
 * Lo pintan el primer render y PanelController::diaAdminAjax.
 * Recibe $agenda de PanelController::agendaPorVeterinario().
 */
require_once __DIR__ . '/../../../helpers/ResumenPanel.php';

$hAg = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$columnasAgenda = $agenda['columnas'];
$totalVeterinarios = count($columnasAgenda);
$muchosVeterinarios = $totalVeterinarios > 3;
$tituloAgenda = $agenda['titulo'] === 'hoy' ? 'Agenda de hoy por veterinario' : 'Agenda del ' . $agenda['titulo'] . ' por veterinario';
$resumenAgenda = $agenda['citas'] . ($agenda['citas'] === 1 ? ' cita' : ' citas') . ' · toca un día del calendario';
if ($muchosVeterinarios) {
    // Decisión del dueño (2026-10-24): con más de tres se ven tres; se dice que hay más a los lados.
    $resumenAgenda = $agenda['citas'] . ($agenda['citas'] === 1 ? ' cita' : ' citas') . ' · ' . $totalVeterinarios . ' veterinarios, desliza para ver a todos';
}
?>
<div class="inicio-cabecera">
    <h2 class="inicio-titulo inicio-titulo--dia" id="inicioTituloDia"><?= $hAg($tituloAgenda) ?></h2>
    <span class="inicio-meta"><?= $hAg($resumenAgenda) ?></span>
</div>
<?php if ($columnasAgenda === []): ?>
    <p class="inicio-meta inicio-vacio">La clínica todavía no tiene veterinarios activos. Se agregan desde Usuarios.</p>
<?php else: ?>
    <div class="inicio-agenda inicio-agenda--<?= $muchosVeterinarios ? 'muchas' : $totalVeterinarios ?>"
         role="region" aria-label="<?= $muchosVeterinarios ? 'Agenda por veterinario; desplázate a los lados para ver a todos' : 'Agenda por veterinario' ?>"<?= $muchosVeterinarios ? ' tabindex="0"' : '' ?>>
        <?php foreach ($columnasAgenda as $columna): ?>
            <?php
            if ($columna['citas'] === 0) {
                $resumenVet = $columna['sin_jornada'] ? 'no atiende este día' : 'sin citas';
            } else {
                $resumenVet = $columna['citas'] . ($columna['citas'] === 1 ? ' cita' : ' citas');
                if ($columna['ocupacion'] !== null) {
                    $resumenVet .= ' · ' . $columna['ocupacion'] . ' % ocupado';
                }
            }
            $cargaAlta = $columna['ocupacion'] !== null && $columna['ocupacion'] >= ResumenPanel::AVISO_PLAN;
            ?>
            <section class="inicio-agenda__vet" aria-label="Agenda de <?= $hAg($columna['nombre']) ?>">
                <header class="inicio-agenda__cabeza">
                    <span class="inicio-ava inicio-ava--<?= (int) $columna['id'] % 5 ?>" aria-hidden="true"><?= $hAg(ResumenPanel::iniciales($columna['nombre'])) ?></span>
                    <span class="inicio-agenda__quien">
                        <strong><?= $hAg($columna['nombre']) ?></strong>
                        <span class="inicio-meta"><?= $hAg($resumenVet) ?></span>
                    </span>
                </header>
                <span class="inicio-barra" aria-hidden="true"><span class="inicio-barra__relleno<?= $cargaAlta ? ' inicio-barra__relleno--aviso' : '' ?> inicio-ancho--<?= ResumenPanel::escala5($columna['ocupacion']) ?>"></span></span>
                <?php if ($columna['aviso'] !== null): ?>
                    <p class="inicio-aviso" role="status">No se pueden mostrar los huecos: <?= $hAg($columna['aviso']) ?></p>
                <?php endif; ?>
                <?php if ($columna['lineas'] === []): ?>
                    <p class="inicio-meta inicio-agenda__vacio"><?= $columna['sin_jornada'] ? 'Sin horario de atención este día.' : 'Sin citas este día.' ?></p>
                <?php else: ?>
                    <ol class="inicio-agenda__filas">
                        <?php foreach ($columna['lineas'] as $linea): ?>
                            <li class="inicio-agenda__fila<?= $linea['tipo'] === 'cita' ? ' inicio-linea--' . $hAg($linea['estado']) : '' ?>">
                                <span class="inicio-agenda__hora"><?= $hAg($linea['hora']) ?></span>
                                <?php if ($linea['tipo'] === 'cita'): ?>
                                    <span class="inicio-cita inicio-cita--compacta<?= $linea['urgencia'] ? ' inicio-cita--urgencia' : '' ?>">
                                        <span class="inicio-cita__texto"><strong><?= $hAg($linea['mascota']) ?></strong><?php if ($linea['texto'] !== ''): ?> · <?= $hAg($linea['texto']) ?><?php endif; ?></span>
                                        <span class="inicio-agenda__punto" title="<?= $hAg(($linea['urgencia'] ? 'Urgencia · ' : '') . $linea['etiqueta']) ?>" aria-hidden="true"></span>
                                        <span class="visualmente-oculto">, <?= $hAg(($linea['urgencia'] ? 'urgencia, ' : '') . mb_strtolower($linea['etiqueta'])) ?></span>
                                    </span>
                                <?php elseif ($linea['tipo'] === 'libre'): ?>
                                    <span class="inicio-hueco inicio-hueco--compacto"><?= $hAg($linea['texto']) ?></span>
                                <?php else: ?>
                                    <span class="inicio-bloqueo inicio-bloqueo--compacto inicio-bloqueo--<?= $hAg($linea['clase']) ?>"><?= $hAg($linea['texto']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
