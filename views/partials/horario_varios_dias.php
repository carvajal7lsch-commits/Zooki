<dialog id="<?= $prefijoTablaHorario ?>modalMultiDia" class="zk-dialogo horario-varios" aria-labelledby="<?= $prefijoTablaHorario ?>mdTitulo">
    <div class="zk-dialogo__form">
        <header class="zk-dialogo__head"><span class="zk-dialogo__icono"><i class="fas fa-calendar-week" aria-hidden="true"></i></span><div><h3 id="<?= $prefijoTablaHorario ?>mdTitulo">Aplicar a varios días</h3><p>Repite los turnos en los días seleccionados.</p></div><button type="button" class="zk-dialogo__cerrar" data-cerrar-modal aria-label="Cerrar">×</button></header>
        <div class="zk-dialogo__cuerpo">
            <div class="md-days-grid">
                <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $n => $d): ?>
                <label class="md-day-chip"><input type="checkbox" class="md-day-check" value="<?= $n ?>"><span><?= $d ?></span></label>
                <?php endforeach; ?>
            </div>
            <?php foreach ($bloques as $periodo => [$etiqueta, $inicio, $fin]): ?>
            <?php $sufijo = $periodo === 'morning' ? 'Morning' : 'Afternoon'; ?>
            <div class="bloque-section" id="<?= $prefijoTablaHorario ?>md<?= $sufijo ?>Section">
                <div class="bloque-header"><label>Turno de <?= mb_strtolower($etiqueta) ?></label><label class="switch-sm"><input type="checkbox" id="<?= $prefijoTablaHorario ?>md<?= $sufijo ?>Activo" data-md-periodo="<?= $periodo ?>" checked><span class="slider round"></span></label></div>
                <div class="bloque-inputs"><input type="time" id="<?= $prefijoTablaHorario ?>md<?= $sufijo ?>Inicio" value="<?= $inicio ?>" step="60"><span>→</span><input type="time" id="<?= $prefijoTablaHorario ?>md<?= $sufijo ?>Fin" value="<?= $fin ?>" step="60"></div>
            </div>
            <?php endforeach; ?>
            <p class="md-error" id="<?= $prefijoTablaHorario ?>mdError" role="status"></p>
        </div>
        <footer class="zk-dialogo__pie"><button type="button" class="coord-btn coord-btn--sec" data-cerrar-modal>Cancelar</button><button type="button" class="coord-btn" id="<?= $prefijoTablaHorario ?>btnAplicarMultiDia">Aplicar horario</button></footer>
    </div>
</dialog>
