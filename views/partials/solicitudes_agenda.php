<?php require_once __DIR__ . '/../../config/App.php'; ?>
<section class="agenda-herramientas__panel" id="agenda-panel-triage" role="tabpanel" aria-labelledby="agenda-tab-triage" data-agenda-solicitudes data-puede-revisar="<?= Contexto::rolActivo() === Roles::VETERINARIO ? '1' : '0' ?>">
    <h3>Revisión de triage e historial de reclasificaciones</h3>
    <label class="zk-label">Mostrar <select class="zk-input" data-solicitudes-filtro><option value="0">Pendientes de revisión</option><option value="1">Con revisión veterinaria</option></select></label>
    <button type="button" class="btn-secondary" data-solicitudes-actualizar>Actualizar solicitudes</button>
    <p data-solicitudes-error role="status"></p>
    <div data-solicitudes-lista></div>
</section>
<script src="js/comando-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/agenda-solicitudes.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
