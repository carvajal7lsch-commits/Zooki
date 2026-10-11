<?php
/** C9.1: el cliente se edita (nombre, teléfono y vínculo) y se ve con sus mascotas en la clínica. */
?>
<button type="button" class="<?= $claseAccion ?>" data-accion="editar-cliente" data-id="<?= (int) $p['id_usuario'] ?>" title="Editar" aria-label="Editar a <?= $e($p['nombre_completo']) ?>"><i class="bi bi-pencil-fill" aria-hidden="true"></i></button>
<button type="button" class="<?= $claseAccion ?>" data-accion="ver-cliente" data-id="<?= (int) $p['id_usuario'] ?>" title="Ver detalle y mascotas" aria-label="Ver a <?= $e($p['nombre_completo']) ?> y sus mascotas"><i class="bi bi-eye-fill" aria-hidden="true"></i></button>
