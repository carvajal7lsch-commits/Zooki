<?php
/**
 * HU-43 — Configurar los horarios de atención de la clínica.
 *
 * Una fila por día con sus bloques de mañana y tarde. Los cambios se guardan
 * solos (public/js/horarios.js) y el resumen muestra cómo queda la semana.
 */
$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$bloques = ['morning' => ['Mañana', '08:00', '12:00'], 'afternoon' => ['Tarde', '14:00', '18:00']];
?>
<div class="horarios">

    <section class="horarios-card horarios-semana" aria-labelledby="horariosTitulo">
        <header class="horarios-card__head">
            <div>
                <h2 id="horariosTitulo">Horario de atención</h2>
                <p>Días y horas en que la agenda ofrece citas.</p>
            </div>
            <div class="horarios-card__acciones">
                <span class="horarios-estado" id="horariosEstado" data-estado="listo" role="status" aria-live="polite">
                    <i class="fas fa-cloud"></i> <span>Los cambios se guardan solos</span>
                </span>
                <button type="button" class="horarios-btn horarios-btn--sec" id="btnVariosDias">
                    <i class="fas fa-calendar-week"></i> Aplicar a varios días
                </button>
            </div>
        </header>

        <div class="horarios-tabla" role="table" aria-label="Horario de atención por día">
            <div class="horarios-fila horarios-fila--cabecera" role="row">
                <span role="columnheader">Día</span>
                <span role="columnheader">Mañana</span>
                <span role="columnheader">Tarde</span>
                <span role="columnheader" class="horarios-col-horas">Horas</span>
            </div>

            <?php foreach ($diasSemana as $n => $dia): ?>
                <div class="horarios-fila horario-dia" data-dia="<?= $n ?>" role="row">
                    <div class="horario-dia__nombre" role="cell">
                        <label class="switch-sm" title="Abrir o cerrar el <?= mb_strtolower($dia) ?>">
                            <input type="checkbox" class="dia-activo" data-dia="<?= $n ?>" checked aria-label="<?= $dia ?> abierto">
                            <span class="slider round"></span>
                        </label>
                        <span><?= $dia ?></span>
                    </div>

                    <?php foreach ($bloques as $periodo => [$etiqueta, $inicio, $fin]): ?>
                        <div class="bloque-section horario-bloque" id="bloque-<?= $periodo ?>-<?= $n ?>" data-etiqueta="<?= $etiqueta ?>" role="cell">
                            <div class="horario-bloque__control">
                                <label class="switch-sm" title="<?= $etiqueta ?>">
                                    <input type="checkbox" class="bloque-activo <?= $periodo ?>-bloque-activo" data-dia="<?= $n ?>" data-periodo="<?= $periodo ?>" checked aria-label="<?= $etiqueta ?> del <?= mb_strtolower($dia) ?>">
                                    <span class="slider round"></span>
                                </label>
                                <div class="bloque-inputs">
                                    <input type="time" class="<?= $periodo ?>-inicio time-picker-input" data-dia="<?= $n ?>" value="<?= $inicio ?>">
                                    <span>-</span>
                                    <input type="time" class="<?= $periodo ?>-fin time-picker-input" data-dia="<?= $n ?>" value="<?= $fin ?>">
                                </div>
                            </div>
                            <div class="validation-error" id="error-<?= $periodo ?>-<?= $n ?>"></div>
                        </div>
                    <?php endforeach; ?>

                    <span class="horario-dia__cerrado" role="cell">Cerrado</span>
                    <span class="horario-dia__horas horarios-col-horas" role="cell" data-horas>—</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="horarios-card horarios-resumen" aria-labelledby="resumenTitulo">
        <header class="horarios-card__head">
            <div>
                <h2 id="resumenTitulo">Resumen de la semana</h2>
                <p>Así queda la agenda con el horario actual.</p>
            </div>
        </header>

        <div class="horarios-resumen__cifras">
            <div class="horarios-cifra">
                <span class="horarios-cifra__valor" id="resumenDias">—</span>
                <span class="horarios-cifra__etiqueta">días abiertos</span>
            </div>
            <div class="horarios-cifra">
                <span class="horarios-cifra__valor" id="resumenHoras">—</span>
                <span class="horarios-cifra__etiqueta">horas a la semana</span>
            </div>
        </div>

        <div class="horarios-hoy" id="resumenHoy" data-abierto="">
            <span class="horarios-hoy__titulo" id="resumenHoyTitulo">Hoy</span>
            <span class="horarios-hoy__estado" id="resumenHoyEstado">—</span>
            <span class="horarios-hoy__detalle" id="resumenHoyDetalle"></span>
        </div>

        <ul class="horarios-notas">
            <li><i class="fas fa-sun"></i> La mañana va de 6:00 a. m. a 12:00 p. m.</li>
            <li><i class="fas fa-moon"></i> La tarde va de 12:00 p. m. a 9:00 p. m.</li>
            <li><i class="fas fa-info-circle"></i> Las citas ya agendadas no se mueven al cambiar el horario.</li>
        </ul>

        <div class="horarios-resumen__pie">
            <button type="button" class="horarios-btn horarios-btn--peligro" id="btnRestaurar">
                <i class="fas fa-undo"></i> Restaurar horario por defecto
            </button>
        </div>
    </aside>

    <!-- Modal: aplicar el mismo horario a varios días -->
    <div id="modalMultiDia" class="md-overlay" hidden>
        <div class="md-card" role="dialog" aria-modal="true" aria-labelledby="mdTitulo">
            <div class="md-header">
                <h3 id="mdTitulo">Aplicar a varios días</h3>
                <button type="button" class="md-close" data-cerrar-modal aria-label="Cerrar">&times;</button>
            </div>
            <div class="md-body">
                <p class="md-hint">Selecciona los días a los que quieres aplicar el mismo horario:</p>
                <div class="md-days-grid">
                    <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $n => $d): ?>
                        <label class="md-day-chip">
                            <input type="checkbox" class="md-day-check" value="<?= $n ?>">
                            <span><?= $d ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($bloques as $periodo => [$etiqueta, $inicio, $fin]):
                    $sufijo = $periodo === 'morning' ? 'Morning' : 'Afternoon'; ?>
                    <div class="bloque-section" id="md<?= $sufijo ?>Section">
                        <div class="bloque-header">
                            <label><?= $etiqueta ?></label>
                            <label class="switch-sm">
                                <input type="checkbox" id="md<?= $sufijo ?>Activo" data-md-periodo="<?= $periodo ?>" checked>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <div class="bloque-inputs">
                            <input type="time" id="md<?= $sufijo ?>Inicio" value="<?= $inicio ?>">
                            <span>-</span>
                            <input type="time" id="md<?= $sufijo ?>Fin" value="<?= $fin ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="md-error" id="mdError"></div>
            </div>
            <div class="md-footer">
                <button type="button" class="btn-secondary" data-cerrar-modal>Cancelar</button>
                <button type="button" class="btn-primary" id="btnAplicarMultiDia">Aplicar a días seleccionados</button>
            </div>
        </div>
    </div>
</div>

<script src="js/time-picker.js"></script>
<script src="js/horarios.js?v=2"></script>
