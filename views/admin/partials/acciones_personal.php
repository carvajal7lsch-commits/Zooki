<?php
/** C1.7/C9: tarjetas y tabla comparten las mismas restricciones de identidad. */
?>
<button type="button" class="action-btn" data-accion="editar" data-id="<?= (int) $p['id_usuario'] ?>" title="Editar" aria-label="Editar"><i class="fas fa-pen" aria-hidden="true"></i></button>
<?php if (!$esYo): ?>
    <button type="button" class="action-btn" data-accion="estado" data-id="<?= (int) $p['id_usuario'] ?>" data-estado="<?= $activo ? 0 : 1 ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" title="<?= $activo ? 'Desactivar en la clínica' : 'Activar en la clínica' ?>" aria-label="<?= $activo ? 'Desactivar' : 'Activar' ?>"><i class="fas <?= $activo ? 'fa-user-slash' : 'fa-user-check' ?>" aria-hidden="true"></i></button>
    <button type="button" class="action-btn" <?php if (!$p['identidad_editable']): ?>disabled<?php endif; ?> data-accion="restablecer" data-id="<?= (int) $p['id_usuario'] ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" title="<?= $p['identidad_editable'] ? 'Restablecer contraseña' : 'Cuenta con otros vínculos: solo el titular cambia la contraseña' ?>" aria-label="Restablecer contraseña"><i class="fas fa-key" aria-hidden="true"></i></button>
<?php endif; ?>
