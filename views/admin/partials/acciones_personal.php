<?php
/**
 * C1.7/C9.1: tarjetas y tabla comparten las mismas restricciones de identidad.
 * Restablecer la contraseña no se ofrece si la persona tiene otro vínculo
 * (decisión del usuario en el recorrido de C8/C9): el servidor lo rechaza igual.
 */
?>
<button type="button" class="<?= $claseAccion ?>" data-accion="editar" data-id="<?= (int) $p['id_usuario'] ?>" title="Editar" aria-label="Editar a <?= $e($p['nombre_completo']) ?>"><i class="bi bi-pencil-fill" aria-hidden="true"></i></button>
<?php if (!$esYo && $p['identidad_editable']): ?>
    <button type="button" class="<?= $claseAccion ?>" data-accion="restablecer" data-id="<?= (int) $p['id_usuario'] ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" title="Restablecer contraseña" aria-label="Restablecer la contraseña de <?= $e($p['nombre_completo']) ?>"><i class="bi bi-key" aria-hidden="true"></i></button>
<?php endif; ?>
