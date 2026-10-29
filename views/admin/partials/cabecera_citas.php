<div class="citas-cabecera directorio-cabecera">
    <div class="head-title-desc">
        <h1 class="users-page-title top-header-title"><i class="bi bi-calendar3 icon-primary" aria-hidden="true"></i>Citas</h1>
        <p class="users-module-desc">Agenda de la clínica por veterinario</p>
    </div>
    <div class="citas-pestanas tabs-wrapper" role="tablist" aria-label="Gestión de citas">
        <?php foreach (['agenda' => 'Agenda', 'triage' => 'Triage', 'urgencias' => 'Urgencias'] as $claveCitas => $tituloCitas): ?>
        <button type="button" role="tab" class="tab-btn" id="agenda-tab-<?= $claveCitas ?>" data-agenda-tab="<?= $claveCitas ?>" aria-controls="agenda-panel-<?= $claveCitas ?>" aria-selected="<?= $claveCitas === 'agenda' ? 'true' : 'false' ?>" tabindex="<?= $claveCitas === 'agenda' ? '0' : '-1' ?>">
            <span class="citas-conteo" data-citas-conteo="<?= $claveCitas ?>">0</span><span><?= $tituloCitas ?></span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
