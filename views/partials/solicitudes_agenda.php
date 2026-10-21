<?php require_once __DIR__ . '/../../config/App.php'; ?>
<details data-agenda-solicitudes data-puede-revisar="<?= Contexto::rolActivo() === Roles::VETERINARIO ? '1' : '0' ?>">
    <summary>Revisión de triage e historial de reclasificaciones</summary>
    <label>Mostrar <select data-solicitudes-filtro><option value="0">Pendientes de revisión</option><option value="1">Con revisión veterinaria</option></select></label>
    <button type="button" class="btn-secondary" data-solicitudes-actualizar>Actualizar solicitudes</button>
    <p data-solicitudes-error role="status"></p>
    <div data-solicitudes-lista></div>
</details>
<script src="js/comando-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/agenda-solicitudes.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . '/reasignaciones_agenda.php'; ?>

<?php require __DIR__ . '/urgencias_agenda.php'; ?>
<?php require __DIR__ . '/llegadas_agenda.php'; ?>
