<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<section class="agenda-herramientas__panel" id="agenda-panel-reasignaciones" role="tabpanel" aria-labelledby="agenda-tab-reasignaciones" hidden data-reasignaciones-agenda>
    <header class="ah-head">
        <div>
            <h3>Propuestas de reasignación</h3>
            <p>Una propuesta no reserva el espacio: al aceptarla se comprueba de nuevo la disponibilidad.</p>
        </div>
    </header>
    <p class="ah-estado" role="status" data-reasignaciones-mensaje></p>
    <div class="ah-lista" data-reasignaciones-lista data-vacio="No tienes propuestas pendientes."></div>
</section>
<script src="js/reasignaciones-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>&amp;r=<?= substr(hash_file('sha256', __DIR__ . '/../../public/js/reasignaciones-agenda.js'), 0, 12) ?>"></script>
<?php endif; ?>
