<?php
require_once __DIR__ . '/../../config/App.php';
/**
 * HU-7.1 — Configurar los horarios de atención de la clínica.
 *
 * Una fila por día con sus bloques de mañana y tarde. Los cambios se guardan
 * solos (public/js/horarios.js) y el resumen muestra cómo queda la semana.
 */
$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$bloques = ['morning' => ['Mañana', '08:00', '12:00'], 'afternoon' => ['Tarde', '14:00', '18:00']];
?>
<div class="horarios" data-clinica="<?= (int) Contexto::clinicaActiva() ?>">

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

        <?php $prefijoTablaHorario = ''; require __DIR__ . '/../partials/tabla_horario_semanal.php'; ?>
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

    <?php require __DIR__ . '/../partials/horario_varios_dias.php'; ?>
</div>

<script src="js/time-picker.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/horarios.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
