<?php
// D: las herramientas de agenda (triage, reasignaciones, urgencias, llegadas) en un solo bloque con pestañas,
// en vez de cuatro secciones sueltas debajo del calendario. Cada panel conserva sus data-* y su JS.
require_once __DIR__ . '/../../config/App.php';
$esVetHerramientas = Contexto::rolActivo() === Roles::VETERINARIO;
// R: admin integra las herramientas en su hoja; el calendario del vet conserva su estructura.
if (!empty($agendaAdminIntegrada) && !$esVetHerramientas) {
    require __DIR__ . '/solicitudes_agenda.php';
    require __DIR__ . '/urgencias_agenda.php';
    echo '<script src="js/agenda-herramientas.js?v=' . htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') . '"></script>';
    return;
}
$pestanasAgenda = ['triage' => ['Triage', 'fas fa-stethoscope']];
if ($esVetHerramientas) {
    $pestanasAgenda['llegadas'] = ['Llegadas', 'fas fa-door-open'];
    $pestanasAgenda['reasignaciones'] = ['Reasignaciones', 'fas fa-exchange-alt'];
}
$pestanasAgenda['urgencias'] = ['Urgencias', 'fas fa-exclamation-triangle'];
if ($esVetHerramientas) {
    $pestanasAgenda = ['urgencias' => ['Rojos', 'fas fa-exclamation-triangle'], 'triage' => ['Triage', 'fas fa-stethoscope'], 'llegadas' => ['Sala', 'fas fa-door-open'], 'reasignaciones' => ['Reasignar', 'fas fa-exchange-alt']];
}
?>
<link rel="stylesheet" href="css/dialogos-zooki.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<section class="agenda-herramientas<?= $esVetHerramientas ? ' agenda-herramientas--tarjetas' : '' ?>" data-agenda-herramientas aria-label="Herramientas de agenda">
    <?php if ($esVetHerramientas): ?>
    <header class="agenda-pendientes__head">
        <div>
            <h3>Pendientes de hoy</h3>
            <p><span data-agenda-pendientes-fecha></span> · lo que requiere acción</p>
        </div>
        <span class="agenda-pendientes__total" data-agenda-pendientes-total aria-label="Total de pendientes">0</span>
    </header>
    <?php endif; ?>
    <div class="agenda-herramientas__tabs" role="tablist" aria-label="Herramientas de agenda">
        <?php $primeraAgenda = true; foreach ($pestanasAgenda as $claveAgenda => [$tituloAgenda, $iconoAgenda]): ?>
        <button type="button" role="tab" class="agenda-herramientas__tab" id="agenda-tab-<?= $claveAgenda ?>" aria-controls="agenda-panel-<?= $claveAgenda ?>" aria-selected="<?= $primeraAgenda ? 'true' : 'false' ?>" tabindex="<?= $primeraAgenda ? '0' : '-1' ?>" data-agenda-tab="<?= $claveAgenda ?>">
            <i class="<?= $iconoAgenda ?>" aria-hidden="true"></i><span><?= $tituloAgenda ?></span>
            <?php if ($esVetHerramientas): ?><span class="agenda-conteo" data-agenda-conteo="<?= $claveAgenda ?>">0</span><?php endif; ?>
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
<script src="js/agenda-tarjetas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>&amp;r=<?= substr(hash_file('sha256', __DIR__ . '/../../public/js/agenda-tarjetas.js'), 0, 12) ?>"></script>
<script src="js/dialogos-zooki.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/agenda-herramientas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
