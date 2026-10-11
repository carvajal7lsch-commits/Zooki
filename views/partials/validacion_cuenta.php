<?php require_once __DIR__ . '/../../helpers/Csrf.php'; ?>
<script src="js/interacciones.js?v=d31"></script>
<script src="js/validacion-cuenta.js?v=d31" data-cuenta-csrf="<?= htmlspecialchars(Csrf::token('cuenta_validacion'), ENT_QUOTES, 'UTF-8') ?>" defer></script>

<script src="js/confirmacion-google.js?v=d22" defer></script>
