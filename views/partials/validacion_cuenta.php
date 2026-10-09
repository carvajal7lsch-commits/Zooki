<?php require_once __DIR__ . '/../../helpers/Csrf.php'; ?>
<script src="js/validacion-cuenta.js?v=d2" data-cuenta-csrf="<?= htmlspecialchars(Csrf::token('cuenta_validacion'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
