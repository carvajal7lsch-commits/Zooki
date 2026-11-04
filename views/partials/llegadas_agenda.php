<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<?php require __DIR__ . '/motivos_revision.php'; ?>
<div hidden data-llegadas-cita data-clinica="<?= (int) Contexto::clinicaActiva() ?>" data-veterinario="<?= (int) Contexto::idUsuario() ?>">
    <div data-llegadas-csrf><?php Csrf::field(); ?></div>
</div>
<script src="js/llegadas-cita.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>&amp;r=<?= substr(hash_file('sha256', __DIR__ . '/../../public/js/llegadas-cita.js'), 0, 12) ?>" defer></script>
<?php endif; ?>
