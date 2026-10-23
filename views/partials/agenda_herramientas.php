<?php
// D: las herramientas de agenda (triage, reasignaciones, urgencias, llegadas) en un solo bloque con pestañas,
// en vez de cuatro secciones sueltas debajo del calendario. Cada panel conserva sus data-* y su JS.
require_once __DIR__ . '/../../config/App.php';
$esVetHerramientas = Contexto::rolActivo() === Roles::VETERINARIO;
$pestanasAgenda = ['triage' => ['Triage', 'fas fa-stethoscope']];
if ($esVetHerramientas) {
    $pestanasAgenda['llegadas'] = ['Llegadas', 'fas fa-door-open'];
    $pestanasAgenda['reasignaciones'] = ['Reasignaciones', 'fas fa-exchange-alt'];
}
$pestanasAgenda['urgencias'] = ['Urgencias', 'fas fa-exclamation-triangle'];
?>
<link rel="stylesheet" href="css/dialogos-zooki.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<section class="agenda-herramientas" data-agenda-herramientas aria-label="Herramientas de agenda">
    <div class="agenda-herramientas__tabs" role="tablist" aria-label="Herramientas de agenda">
        <?php $primeraAgenda = true; foreach ($pestanasAgenda as $claveAgenda => [$tituloAgenda, $iconoAgenda]): ?>
        <button type="button" role="tab" class="agenda-herramientas__tab" id="agenda-tab-<?= $claveAgenda ?>" aria-controls="agenda-panel-<?= $claveAgenda ?>" aria-selected="<?= $primeraAgenda ? 'true' : 'false' ?>" tabindex="<?= $primeraAgenda ? '0' : '-1' ?>" data-agenda-tab="<?= $claveAgenda ?>">
            <i class="<?= $iconoAgenda ?>" aria-hidden="true"></i><span><?= $tituloAgenda ?></span>
        </button>
        <?php $primeraAgenda = false; endforeach; ?>
    </div>
    <div class="agenda-herramientas__cuerpo">
        <?php require __DIR__ . '/solicitudes_agenda.php'; ?>
        <?php require __DIR__ . '/llegadas_agenda.php'; ?>
        <?php require __DIR__ . '/reasignaciones_agenda.php'; ?>
        <?php require __DIR__ . '/urgencias_agenda.php'; ?>
    </div>
</section>
<script src="js/dialogos-zooki.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/agenda-herramientas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
