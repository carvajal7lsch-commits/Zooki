<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<section class="zk-card" data-reasignaciones-agenda>
    <h3>Propuestas de reasignación</h3>
    <p>Una propuesta no reserva el espacio. Al aceptar se comprueba de nuevo la disponibilidad.</p>
    <button type="button" class="btn-secondary" data-reasignaciones-actualizar>Actualizar propuestas</button>
    <p role="status" data-reasignaciones-mensaje></p>
    <div data-reasignaciones-lista></div>
</section>
<script src="js/reasignaciones-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
