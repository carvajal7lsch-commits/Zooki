<?php require_once __DIR__ . '/../../config/App.php'; ?>
<section class="agenda-herramientas__panel" id="agenda-panel-triage" role="tabpanel" aria-labelledby="agenda-tab-triage" data-agenda-solicitudes data-puede-revisar="<?= Contexto::rolActivo() === Roles::VETERINARIO ? '1' : '0' ?>">
    <header class="ah-head">
        <div>
            <h3>Revisión de triage</h3>
            <p><?= Contexto::rolActivo() === Roles::VETERINARIO ? 'Confirma o ajusta el nivel provisional, también después de reservar.' : 'Solicitudes que esperan la revisión del veterinario.' ?></p>
        </div>
        <div class="ah-acciones">
            <label class="ah-filtro"><span class="ah-oculto">Mostrar</span>
                <select data-solicitudes-filtro><option value="0">Pendientes</option><option value="1">Revisadas</option></select>
            </label>
            <button type="button" class="zk-icono" data-solicitudes-actualizar title="Actualizar" aria-label="Actualizar solicitudes"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
        </div>
    </header>
    <p class="ah-estado" data-solicitudes-error role="status"></p>
    <div class="ah-lista" data-solicitudes-lista data-vacio="No hay solicitudes en esta vista."></div>
</section>
<script src="js/comando-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/agenda-solicitudes.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
