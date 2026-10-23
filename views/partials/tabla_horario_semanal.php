<?php
// HU-7.1/HU-4.11: la clínica y el veterinario comparten tabla y selectores.
$prefijoTablaHorario = $prefijoTablaHorario ?? '';
$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$bloques = ['morning' => ['Mañana', '08:00', '12:00'], 'afternoon' => ['Tarde', '14:00', '18:00']];
?>
<div class="horarios-tabla" role="table" aria-label="Horario semanal"<?= $prefijoTablaHorario !== '' ? ' data-franjas' : '' ?>>
    <div class="horarios-fila horarios-fila--cabecera" role="row">
        <span role="columnheader">Día</span><span role="columnheader">Mañana</span><span role="columnheader">Tarde</span><span role="columnheader" class="horarios-col-horas">Horas</span>
    </div>
    <?php foreach ($diasSemana as $n => $dia): ?>
    <div class="horarios-fila horario-dia" data-dia="<?= $n ?>" data-dia-semana="<?= $n ?>" role="row">
        <div class="horario-dia__nombre" role="cell">
            <label class="switch-sm"><input type="checkbox" class="dia-activo" data-dia="<?= $n ?>" data-dia-activo checked aria-label="<?= $dia ?> activo"><span class="slider round"></span></label><span><?= $dia ?></span>
        </div>
        <?php foreach ($bloques as $periodo => [$etiqueta, $inicio, $fin]): ?>
        <div class="bloque-section horario-bloque" id="<?= $prefijoTablaHorario ?>bloque-<?= $periodo ?>-<?= $n ?>" data-etiqueta="<?= $etiqueta ?>" data-turno="<?= $periodo ?>" role="cell">
            <div class="horario-bloque__control">
                <label class="switch-sm"><input type="checkbox" class="bloque-activo <?= $periodo ?>-bloque-activo" data-dia="<?= $n ?>" data-periodo="<?= $periodo ?>" checked aria-label="Turno de <?= mb_strtolower($etiqueta) ?> del <?= mb_strtolower($dia) ?>"><span class="slider round"></span></label>
                <div class="bloque-inputs">
                    <input type="time" class="<?= $periodo ?>-inicio time-picker-input" data-dia="<?= $n ?>" data-franja-campo="hora_inicio" step="60" value="<?= $inicio ?>" aria-label="Inicio de <?= mb_strtolower($etiqueta) ?> del <?= mb_strtolower($dia) ?>">
                    <span aria-hidden="true">→</span>
                    <input type="time" class="<?= $periodo ?>-fin time-picker-input" data-dia="<?= $n ?>" data-franja-campo="hora_fin" step="60" value="<?= $fin ?>" aria-label="Fin de <?= mb_strtolower($etiqueta) ?> del <?= mb_strtolower($dia) ?>">
                </div>
            </div>
            <div class="validation-error" id="<?= $prefijoTablaHorario ?>error-<?= $periodo ?>-<?= $n ?>"></div>
        </div>
        <?php endforeach; ?>
        <span class="horario-dia__cerrado" role="cell"><?= $prefijoTablaHorario === '' ? 'Cerrado' : 'Libre' ?></span><span class="horario-dia__horas horarios-col-horas" role="cell" data-horas>—</span>
        <span class="horario-dia__aviso" data-horario-especial hidden role="status"></span>
    </div>
    <?php endforeach; ?>
</div>
