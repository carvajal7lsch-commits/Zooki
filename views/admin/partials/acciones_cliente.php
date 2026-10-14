<?php
/**
 * C9.1: el cliente se edita (nombre, teléfono y vínculo) y se ve con sus
 * mascotas en la clínica. RN-115: desvinculado, ya no se edita. M5 C3: si aún
 * no activa su cuenta, la clínica le reenvía el enlace de activación.
 */
?>
<?php if ($activo): ?>
<button type="button" class="<?= $claseAccion ?>" data-accion="editar-cliente" data-id="<?= (int) $p['id_usuario'] ?>" title="Editar" aria-label="Editar a <?= $e($p['nombre_completo']) ?>"><i class="bi bi-pencil-fill" aria-hidden="true"></i></button>
<?php endif; ?>
<?php if ($activo && (int) $p['pendiente_activacion'] === 1): ?>
<button type="button" class="<?= $claseAccion ?>" data-accion="reenviar-activacion" data-id="<?= (int) $p['id_usuario'] ?>" title="Reenviar enlace de activación" aria-label="Reenviar a <?= $e($p['nombre_completo']) ?> el enlace para activar su cuenta"><i class="bi bi-envelope-arrow-up-fill" aria-hidden="true"></i></button>
<?php endif; ?>
<button type="button" class="<?= $claseAccion ?>" data-accion="ver-cliente" data-id="<?= (int) $p['id_usuario'] ?>" title="Ver detalle y mascotas" aria-label="Ver a <?= $e($p['nombre_completo']) ?> y sus mascotas"><i class="bi bi-eye-fill" aria-hidden="true"></i></button>
