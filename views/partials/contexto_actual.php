<?php
/**
 * HU-T.17 — Contexto activo y enlace para cambiarlo sin cerrar sesión.
 * Se incluye en los layouts; el layout carga css/contexto.css.
 */
require_once __DIR__ . '/../../helpers/Contexto.php';

$__ctx = Contexto::actual();
if ($__ctx !== null):
?>
<span class="contexto-actual" title="<?= htmlspecialchars(Contexto::etiqueta($__ctx)) ?>">
    <span class="contexto-actual__etiqueta"><?= htmlspecialchars(Contexto::etiqueta($__ctx)) ?></span>
    <?php if (Contexto::puedeCambiar()): ?>
        <a class="contexto-actual__cambiar" href="index.php?action=seleccionar_contexto">Cambiar</a>
    <?php endif; ?>
</span>
<?php endif; ?>
