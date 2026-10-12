<?php require_once __DIR__ . '/../../helpers/Csrf.php'; ?>
<?php require_once __DIR__ . '/../../config/App.php'; ?>
<script src="js/interacciones.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/validacion-cuenta.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" data-cuenta-csrf="<?= htmlspecialchars(Csrf::token('cuenta_validacion'), ENT_QUOTES, 'UTF-8') ?>" defer></script>

<script src="js/confirmacion-google.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
