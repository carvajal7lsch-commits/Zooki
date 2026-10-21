<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<section class="agenda-herramientas__panel" id="agenda-panel-reasignaciones" role="tabpanel" aria-labelledby="agenda-tab-reasignaciones" hidden data-reasignaciones-agenda>
    <header class="ah-head">
        <div>
            <h3>Propuestas de reasignación</h3>
            <p>Una propuesta no reserva el espacio: al aceptarla se comprueba de nuevo la disponibilidad.</p>
        </div>
        <div class="ah-acciones">
            <button type="button" class="zk-icono" data-reasignaciones-actualizar title="Actualizar" aria-label="Actualizar propuestas"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
        </div>
    </header>
    <p class="ah-estado" role="status" data-reasignaciones-mensaje></p>
    <div class="ah-lista" data-reasignaciones-lista data-vacio="No tienes propuestas pendientes."></div>
</section>
<script src="js/reasignaciones-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
